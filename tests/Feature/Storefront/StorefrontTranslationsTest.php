<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

/**
 * Guards the storefront translation catalogue (lang/{en,ar}/storefront.php),
 * converted from the Next.js app's next-intl messages.
 *
 * The existing suite does NOT cover this: LangFilesParityTest globs only
 * app/Modules/{module}/Resources/lang/en/*.php, so the root lang/ directory —
 * where the storefront catalogue lives — is unchecked by it.
 *
 * Everything is nested under a single `storefront` group on purpose: three of the
 * source groups (auth, discovery, loyalty) collide with existing root lang files
 * that the API and admin panel use.
 */
function storefrontLang(string $locale): array
{
    return Arr::dot((array) include base_path("lang/{$locale}/storefront.php"));
}

it('keeps the EN and AR storefront catalogues in exact key parity', function (): void {
    $en = storefrontLang('en');
    $ar = storefrontLang('ar');

    expect(array_diff(array_keys($en), array_keys($ar)))->toBe([], 'Keys present in EN but missing from AR');
    expect(array_diff(array_keys($ar), array_keys($en)))->toBe([], 'Keys present in AR but missing from EN');
    expect($en)->not->toBeEmpty();
})->group('storefront', 'i18n');

it('has no empty translations in either locale', function (string $locale): void {
    $empty = array_keys(array_filter(
        storefrontLang($locale),
        fn ($value) => trim((string) $value) === '',
    ));

    expect($empty)->toBe([]);
})->with(['en', 'ar'])->group('storefront', 'i18n');

/**
 * The converter had to rewrite ICU syntax into Laravel's. Any leftover ICU
 * renders as literal "{count, plural, ...}" in the page — visible to the user and
 * easy to miss, since it neither throws nor fails a smoke test.
 */
it('leaves no raw ICU syntax behind', function (string $locale): void {
    $icu = array_keys(array_filter(
        storefrontLang($locale),
        fn ($value) => (bool) preg_match('/\{\w+,\s*(plural|select)/', (string) $value),
    ));

    expect($icu)->toBe([]);
})->with(['en', 'ar'])->group('storefront', 'i18n');

it('uses Laravel :placeholder style rather than ICU {placeholder}', function (string $locale): void {
    $ambiguous = array_keys(array_filter(
        storefrontLang($locale),
        // {0}/{1}/{2} are legitimate trans_choice range markers; {word} is not.
        fn ($value) => (bool) preg_match('/\{[A-Za-z_]\w*\}/', (string) $value),
    ));

    expect($ambiguous)->toBe([]);
})->with(['en', 'ar'])->group('storefront', 'i18n');

it('resolves English plurals through trans_choice', function (): void {
    App::setLocale('en');

    expect(trans_choice('storefront.offers.countdown.days', 1, ['count' => 1]))->toBe('1 day');
    expect(trans_choice('storefront.offers.countdown.days', 5, ['count' => 5]))->toBe('5 days');
})->group('storefront', 'i18n');

/**
 * Arabic has six CLDR plural forms. The first converter pass only handled
 * one/other and silently left every Arabic plural as raw ICU, so this asserts
 * each form individually rather than trusting the catalogue.
 */
it('resolves all six Arabic plural forms through trans_choice', function (int $count, string $expected): void {
    App::setLocale('ar');

    expect(trans_choice('storefront.offers.countdown.days', $count, ['count' => $count]))->toBe($expected);
})->with([
    'zero' => [0, '0 يوم'],
    'one' => [1, '1 يوم'],
    'two' => [2, '2 يومان'],
    'few' => [5, '5 أيام'],
    'many' => [20, '20 يوماً'],
    'other' => [150, '150 يوم'],
])->group('storefront', 'i18n');

it('substitutes placeholders in both locales', function (): void {
    App::setLocale('en');
    expect(Lang::get('storefront.nav.language'))->not->toContain(':');

    App::setLocale('ar');
    $rendered = trans_choice('storefront.rating_aria', 3, ['avg' => '4.5', 'count' => 3]);

    expect($rendered)
        ->toContain('4.5')
        ->toContain('3')
        ->not->toContain(':avg')
        ->not->toContain(':count');
})->group('storefront', 'i18n');
