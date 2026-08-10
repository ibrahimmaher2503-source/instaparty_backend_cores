<?php

declare(strict_types=1);

/**
 * One-off converter: next-intl JSON messages -> Laravel lang files.
 *
 *   apps/frontend/messages/{en,ar}.json  ->  lang/{en,ar}/storefront.php
 *
 * Why one nested file per locale rather than 33 files: three of the JSON's
 * top-level groups (auth, discovery, loyalty) collide with existing root lang
 * files that serve the API and admin panel, and those are live (they even call
 * base_path()). Nesting everything under a single `storefront` group gives
 * collision-proof keys — __('storefront.nav.language') — with no translation
 * namespace to register, which matters because both registration points
 * (AppServiceProvider, bootstrap/providers.php) currently carry other sessions'
 * uncommitted work.
 *
 * Transformations:
 *   {name}                                    -> :name
 *   {n, plural, one {# x} other {# ys}}       -> {1} :n x|[2,*] :n ys
 *   ...preserving any prefix/suffix around the plural block by duplicating it
 *      into both branches, which is what Laravel's trans_choice requires.
 */
$root = 'C:/instaparty_backend_cores';
$source = $root.'/apps/frontend/messages';
$stats = ['strings' => 0, 'placeholders' => 0, 'plurals' => 0];

/**
 * Reads a brace-balanced {...} starting at $i (which must point at '{').
 * Returns [innerContent, indexAfterClosingBrace].
 */
function readBraced(string $s, int $i): array
{
    $depth = 0;
    $start = $i;

    for ($n = strlen($s); $i < $n; $i++) {
        if ($s[$i] === '{') {
            $depth++;
        } elseif ($s[$i] === '}') {
            $depth--;

            if ($depth === 0) {
                return [substr($s, $start + 1, $i - $start - 1), $i + 1];
            }
        }
    }

    return [substr($s, $start + 1), $i];
}

/**
 * ICU plural -> Laravel choice syntax, handling the full CLDR form set.
 *
 * Arabic uses all six forms (zero/one/two/few/many/other); English uses two. A
 * regex over one/other silently leaves Arabic plurals as raw ICU, which renders
 * literal "{count, plural, ...}" to the user, so this parses braces properly.
 *
 * CLDR form -> Laravel range:
 *   zero {0}   one {1}   two {2}   few [3,10]   many [11,99]   other [N,*]
 * where N is one past the highest form actually present.
 */
function convertPlural(string $value, array &$stats): string
{
    while (($pos = preg_match('/\{(\w+),\s*plural,/', $value, $m, PREG_OFFSET_CAPTURE)) === 1) {
        $var = $m[1][0];
        $blockStart = $m[0][1];

        [$inner, $after] = readBraced($value, $blockStart);

        // Strip the leading "var, plural," so only the form list remains.
        $forms = [];
        $rest = preg_replace('/^\s*\w+\s*,\s*plural\s*,/', '', $inner);

        $i = 0;
        $len = strlen($rest);

        while ($i < $len) {
            if (! preg_match('/\G\s*(=?\w+)\s*/', $rest, $fm, 0, $i)) {
                break;
            }

            $form = $fm[1];
            $i += strlen($fm[0]);

            if ($i >= $len || $rest[$i] !== '{') {
                break;
            }

            [$content, $i] = readBraced($rest, $i);
            $forms[$form] = str_replace('#', ':'.$var, $content);
        }

        if ($forms === []) {
            break;
        }

        $stats['plurals']++;

        $order = ['zero', 'one', 'two', 'few', 'many', 'other'];
        $ranges = ['zero' => '{0}', 'one' => '{1}', 'two' => '{2}', 'few' => '[3,10]', 'many' => '[11,99]'];

        $highest = 0;
        foreach (['zero' => 1, 'one' => 2, 'two' => 3, 'few' => 11, 'many' => 100] as $form => $next) {
            if (isset($forms[$form])) {
                $highest = $next;
            }
        }

        $branches = [];
        foreach ($order as $form) {
            if (! isset($forms[$form])) {
                continue;
            }

            $prefix = $form === 'other' ? '['.$highest.',*]' : $ranges[$form];
            $branches[] = $prefix."\x02".$forms[$form];
        }

        $value = substr($value, 0, $blockStart)
            ."\x00PLURAL\x01".implode("\x01", $branches)."\x00"
            .substr($value, $after);
    }

    return $value;
}

/**
 * Finishes a plural conversion once surrounding text is known, duplicating any
 * prefix/suffix into both branches (Laravel splits the WHOLE message on '|').
 */
function finalisePlural(string $value): string
{
    if (! str_contains($value, "\x00PLURAL\x01")) {
        return $value;
    }

    if (! preg_match('/^(.*)\x00PLURAL\x01(.*?)\x00(.*)$/s', $value, $m)) {
        return $value;
    }

    [, $prefix, $body, $suffix] = $m;

    $branches = [];

    foreach (explode("\x01", $body) as $branch) {
        [$range, $text] = explode("\x02", $branch, 2);
        // Laravel splits the WHOLE message on '|', so any text surrounding the
        // plural block has to be duplicated into every branch.
        $branches[] = $range.' '.$prefix.$text.$suffix;
    }

    return implode('|', $branches);
}

function convertValue(string $value, array &$stats): string
{
    $stats['strings']++;

    $value = convertPlural($value, $stats);

    // Remaining simple {placeholder} -> :placeholder.
    // Requires an identifier start, so the {0}/{1}/{2} range markers that
    // convertPlural() has already emitted are left alone.
    $value = preg_replace_callback('/\{([A-Za-z_]\w*)\}/', function (array $m) use (&$stats): string {
        $stats['placeholders']++;

        return ':'.$m[1];
    }, $value) ?? $value;

    return finalisePlural($value);
}

function convertTree(array $tree, array &$stats): array
{
    $out = [];

    foreach ($tree as $key => $value) {
        $out[$key] = is_array($value)
            ? convertTree($value, $stats)
            : convertValue((string) $value, $stats);
    }

    return $out;
}

/**
 * Pretty-prints a nested array as PHP short-array syntax.
 */
function export(array $data, int $indent = 1): string
{
    $pad = str_repeat('    ', $indent);
    $lines = [];

    foreach ($data as $key => $value) {
        $k = "'".str_replace("'", "\\'", (string) $key)."'";

        if (is_array($value)) {
            $lines[] = $pad.$k.' => ['."\n".export($value, $indent + 1).$pad.'],';

            continue;
        }

        $v = "'".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value)."'";
        $lines[] = $pad.$k.' => '.$v.',';
    }

    return implode("\n", $lines)."\n";
}

/**
 * Flattens to dotted keys for the parity check.
 */
function flatten(array $tree, string $prefix = ''): array
{
    $out = [];

    foreach ($tree as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $out += flatten($value, $full);

            continue;
        }

        $out[$full] = $value;
    }

    return $out;
}

/**
 * Strings the Blade storefront needs that the Next.js app never had.
 *
 * They live here rather than being hand-edited into lang/*/ /*storefront.php,
 * because this script overwrites those files wholesale — a manual edit would be
 * silently lost the next time anyone regenerates the catalogue.
 *
 * `remember_me` exists because moving from long-lived HttpOnly tokens to Laravel
 * sessions would otherwise sign customers out after SESSION_LIFETIME.
 */
const WEB_ONLY_ADDITIONS = [
    'en' => [
        'common' => ['remember_me' => 'Remember me'],
    ],
    'ar' => [
        'common' => ['remember_me' => 'تذكرني'],
    ],
];

/**
 * Recursive merge that lets additions sit alongside converted keys.
 */
function mergeAdditions(array $base, array $additions): array
{
    foreach ($additions as $key => $value) {
        $base[$key] = is_array($value) && isset($base[$key]) && is_array($base[$key])
            ? mergeAdditions($base[$key], $value)
            : $value;
    }

    return $base;
}

$converted = [];

foreach (['en', 'ar'] as $locale) {
    $json = json_decode(file_get_contents($source."/{$locale}.json"), true, 512, JSON_THROW_ON_ERROR);
    $converted[$locale] = mergeAdditions(convertTree($json, $stats), WEB_ONLY_ADDITIONS[$locale]);

    $header = <<<'PHP'
<?php

declare(strict_types=1);

/*
 * Storefront (Blade) translations.
 *
 * Converted from the Next.js app's next-intl messages. Nested under a single
 * `storefront` group deliberately: three of the source groups (auth, discovery,
 * loyalty) collide with existing root lang files used by the API and admin panel.
 * Reference as __('storefront.nav.language').
 *
 * Placeholders use Laravel's :name style. Pluralised strings use trans_choice
 * syntax and MUST be read with trans_choice(), not __().
 */

return [

PHP;

    file_put_contents(
        $root."/lang/{$locale}/storefront.php",
        $header.export($converted[$locale])."];\n"
    );
}

// ---- Parity check -------------------------------------------------------
$enKeys = array_keys(flatten($converted['en']));
$arKeys = array_keys(flatten($converted['ar']));

$missingAr = array_diff($enKeys, $arKeys);
$missingEn = array_diff($arKeys, $enKeys);

$emptyAr = array_filter(flatten($converted['ar']), fn ($v) => trim((string) $v) === '');

echo 'Converted   : '.count($enKeys).' EN keys, '.count($arKeys)." AR keys\n";
echo 'Placeholders: '.$stats['placeholders']." converted to :name style\n";
echo 'Plurals     : '.$stats['plurals']." ICU blocks converted to trans_choice syntax\n";
echo 'Missing AR  : '.(count($missingAr) ?: 'none').(count($missingAr) ? ' -> '.implode(', ', array_slice($missingAr, 0, 10)) : '')."\n";
echo 'Missing EN  : '.(count($missingEn) ?: 'none').(count($missingEn) ? ' -> '.implode(', ', array_slice($missingEn, 0, 10)) : '')."\n";
echo 'Empty AR    : '.(count($emptyAr) ?: 'none').(count($emptyAr) ? ' -> '.implode(', ', array_slice(array_keys($emptyAr), 0, 10)) : '')."\n";
