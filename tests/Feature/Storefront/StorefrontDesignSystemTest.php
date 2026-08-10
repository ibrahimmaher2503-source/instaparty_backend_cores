<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Actions\GetActiveDesignTokensAction;
use App\Modules\Shared\Domain\Models\DesignToken;
use App\Modules\Shared\Domain\Schemas\DesignTokenSchema;
use Illuminate\Support\Facades\Cache;

/**
 * Guards the storefront's visual foundation. Every failure mode here is silent:
 * the page still returns 200, so only an assertion catches it.
 */

/**
 * resources/css/app.css is the FILAMENT admin stylesheet — it carries Filament
 * resets, a `Cairo` --font-sans, and v3/v4 compatibility patches. The storefront
 * layout shipped it to customers at first, which both bloated the page and
 * overrode the Inter/Tajawal type system. Nothing about the rendered page looked
 * broken enough to notice.
 */
it('does not ship the Filament admin stylesheet to customers', function (): void {
    $html = $this->get('/en')->getContent();

    expect($html)
        ->toContain('storefront.css')
        ->not->toContain('resources/css/app.css');
})->group('storefront', 'design');

it('emits the design tokens as CSS custom properties', function (): void {
    $html = $this->get('/en')->getContent();

    expect($html)
        ->toContain('--color-primary-500:')
        ->toContain('--color-ink-900:')
        ->toContain('--color-danger:')
        ->toContain('--radius-md:');
})->group('storefront', 'design');

/**
 * The palette is admin-editable (Shared\Domain\Models\DesignToken). If the layout
 * ever hardcodes it, re-branding silently stops working while every page still
 * renders correctly in the default palette — so this changes the active row and
 * asserts the page follows.
 */
it('renders the palette from the active token row, not hardcoded values', function (): void {
    $tokens = DesignTokenSchema::default();
    $tokens['colors']['primary']['500'] = '#0b7285';

    DesignToken::query()->update(['is_active' => false]);
    DesignToken::query()->create([
        'public_id' => (string) Str::ulid(),
        'name' => 'test-rebrand',
        'is_active' => true,
        'tokens' => $tokens,
    ]);

    Cache::forget(GetActiveDesignTokensAction::CACHE_KEY);

    expect($this->get('/en')->getContent())
        ->toContain('--color-primary-500:#0b7285')
        ->not->toContain('--color-primary-500:#831843');
})->group('storefront', 'design');

/**
 * The Tajawal Parity Rule: no Arabic text ever renders in Inter. Implemented by
 * re-pointing --font-sans on [dir="rtl"] in one place, so no component chooses a
 * font itself and none can forget to.
 */
it('switches the whole document font for Arabic rather than per component', function (): void {
    $html = $this->get('/ar')->getContent();

    expect($html)
        ->toContain('dir="rtl"')
        ->toContain('--sf-font-arabic:')
        ->toContain("[dir='rtl']");
})->group('storefront', 'design', 'locale');

/**
 * RTL correctness cannot be caught by a rendering test — a page using `ml-4`
 * returns 200 and looks fine in English while being mirrored wrong in Arabic.
 * Tailwind's logical utilities (ms/me/ps/pe/start/end/text-start/text-end) flip
 * automatically; the physical ones never do.
 */
it('uses only direction-agnostic spacing utilities in storefront views', function (): void {
    $offenders = [];

    foreach (['resources/views/storefront', 'resources/views/components/storefront'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir)));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            preg_match_all(
                '/(?<![\w-])(?:m[lr]|p[lr])-\d|(?<![\w-])text-(?:left|right)(?![\w-])|(?<![\w-])border-[lr](?![\w-])|(?<![\w-])rounded-[lr](?![\w-])/',
                $contents,
                $matches
            );

            foreach ($matches[0] as $match) {
                $offenders[] = $file->getFilename().': '.$match;
            }
        }
    }

    expect($offenders)->toBe([]);
})->group('storefront', 'design', 'locale');
