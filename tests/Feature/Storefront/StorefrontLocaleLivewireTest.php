<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Middleware\SetStorefrontLocaleMiddleware;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Support\LocaleProbeComponent;

/**
 * Guards locale persistence across Livewire interactions for the whole storefront.
 *
 * Livewire posts every interaction to POST /livewire/update, a route carrying no
 * {locale} URL prefix. The obvious worry is that an Arabic page reverts to English
 * on the first filter change, pagination click, or wishlist toggle.
 *
 * It does not, and these tests document why: Livewire 3 writes the resolved locale
 * into the component snapshot memo on dehydrate and restores it on hydrate
 * (Livewire\Features\SupportLocales\SupportLocales — a two-line hook). The snapshot
 * emitted for /ar/... literally contains "locale":"ar". No persistent-middleware
 * registration is needed. This WAS a real problem in Livewire 2; it is not in v3.
 *
 * These tests are kept as a regression guard, since the whole storefront's bilingual
 * behaviour rests on it: a Livewire major upgrade, or anything that disables the
 * SupportLocales hook, breaks every AR page silently and these tests catch it.
 *
 * Two things are load-bearing in how this is tested:
 *  1. It must be a REAL HTTP round-trip. Livewire::test() cannot exercise the path.
 *  2. The locale must be reset between the GET and the POST — see callProbe().
 *     Without that reset these tests pass even when the mechanism is removed.
 */
beforeEach(function (): void {
    Livewire::component('locale-probe', LocaleProbeComponent::class);

    Lang::addLines(['storefront_probe.greeting' => 'Hello'], 'en');
    Lang::addLines(['storefront_probe.greeting' => 'مرحبا'], 'ar');

    Route::middleware(['web', SetStorefrontLocaleMiddleware::class])
        ->prefix('{locale}')
        ->whereIn('locale', SetStorefrontLocaleMiddleware::SUPPORTED_LOCALES)
        ->get('_locale-probe', fn () => Blade::render('@livewire(\'locale-probe\')'));
});

/**
 * Pulls the wire:snapshot payload out of rendered component HTML.
 */
function probeSnapshot(string $html): string
{
    expect($html)->toMatch('/wire:snapshot=/');

    preg_match('/wire:snapshot="([^"]*)"/', $html, $matches);

    return html_entity_decode($matches[1], ENT_QUOTES);
}

/**
 * Replays a Livewire interaction the way the browser does — a real POST to the
 * update endpoint, with no locale anywhere in the URL.
 *
 * $resetLocaleTo is load-bearing, not cosmetic. Laravel reuses one application
 * instance for every request inside a single test, so App::getLocale() would
 * still hold whatever the preceding GET set — masking the very regression this
 * file exists to catch. In production each request boots fresh and the locale
 * starts at the default, so we reset it here to make the POST honest. Reset to
 * a locale *different* from the expected one, or the assertion proves nothing.
 */
function callProbe(string $snapshot, string $method, string $resetLocaleTo): array
{
    App::setLocale($resetLocaleTo);

    return test()->postJson('/livewire/update', [
        '_token' => csrf_token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) [],
            'calls' => [['path' => '', 'method' => $method, 'params' => []]],
        ]],
    ])->json();
}

it('renders the storefront probe in Arabic from the URL prefix', function (): void {
    $response = $this->get('/ar/_locale-probe');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('مرحبا')
        ->toContain('>ar<');
})->group('storefront', 'locale');

it('keeps Arabic through a real Livewire update round-trip', function (): void {
    $snapshot = probeSnapshot($this->get('/ar/_locale-probe')->getContent());

    $html = callProbe($snapshot, 'bump', resetLocaleTo: 'en')['components'][0]['effects']['html'];

    // The interaction itself must have worked...
    expect($html)->toContain('>1<');

    // ...and the locale must NOT have silently reverted to the default.
    expect($html)
        ->toContain('مرحبا')
        ->toContain('>ar<')
        ->not->toContain('Hello');
})->group('storefront', 'locale');

it('keeps English through a real Livewire update round-trip', function (): void {
    $snapshot = probeSnapshot($this->get('/en/_locale-probe')->getContent());

    $html = callProbe($snapshot, 'bump', resetLocaleTo: 'ar')['components'][0]['effects']['html'];

    expect($html)
        ->toContain('Hello')
        ->toContain('>en<')
        ->not->toContain('مرحبا');
})->group('storefront', 'locale');
