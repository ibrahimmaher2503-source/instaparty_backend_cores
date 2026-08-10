<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

/**
 * Every storefront.* key referenced by a Blade view must exist in BOTH locales.
 *
 * A missing key does not throw in Laravel — it renders the key itself, so
 * "storefront.common.remember_me" ships to the user as visible text. That is exactly
 * what happened while building the auth pages, and it was caught by hand; this test
 * catches it automatically, and matters more as the remaining ~30 pages land.
 *
 * Scans source rather than rendering pages, so a key on a branch that only appears in
 * a rare state (validation error, empty result) is still covered.
 */
function storefrontViewKeys(): array
{
    $views = [];

    foreach (['resources/views/storefront', 'resources/views/components/storefront'] as $dir) {
        if (! is_dir(base_path($dir))) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir)));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $views[] = $file->getPathname();
            }
        }
    }

    $keys = [];

    foreach (array_unique($views) as $view) {
        $contents = (string) file_get_contents($view);

        // __('storefront.x.y') and trans_choice('storefront.x.y')
        preg_match_all(
            '/(?:__|trans_choice)\(\s*[\'"](storefront\.[a-z0-9_.]+)[\'"]/i',
            $contents,
            $matches
        );

        foreach ($matches[1] as $key) {
            $keys[$key][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $view);
        }
    }

    return $keys;
}

it('resolves every storefront translation key used in Blade views', function (string $locale): void {
    App::setLocale($locale);

    $keys = storefrontViewKeys();

    expect($keys)->not->toBeEmpty('No storefront.* keys found — check the scanner');

    $missing = [];

    foreach ($keys as $key => $usedIn) {
        if (! Lang::has($key)) {
            $missing[] = $key.'  (used in '.implode(', ', array_unique($usedIn)).')';
        }
    }

    expect($missing)->toBe([]);
})->with(['en', 'ar'])->group('storefront', 'i18n');
