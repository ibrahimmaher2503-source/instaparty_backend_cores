<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Actions\GetBrandingAction;
use App\Modules\Shared\Application\Actions\SaveBrandingAction;
use App\Modules\Shared\Domain\Models\BrandingSetting;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    Cache::forget(GetBrandingAction::CACHE_KEY.':en');
    Cache::forget(GetBrandingAction::CACHE_KEY.':ar');
    BrandingSetting::query()->delete();
});

it('returns default branding when no row exists yet', function (): void {
    getJson('/api/v1/theme/branding', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.site_name', 'InstaParty')
        ->assertJsonStructure([
            'data' => [
                'public_id', 'site_name', 'tagline', 'address_line',
                'support_email', 'support_phone', 'whatsapp_number',
                'social',
                'assets' => ['logo_light', 'logo_dark', 'favicon', 'og_image', 'app_store_badge', 'play_store_badge'],
                'updated_at',
            ],
        ]);
})->group('shared', 'branding');

it('returns translated site_name based on Accept-Language', function (): void {
    app(SaveBrandingAction::class)->execute([
        'site_name' => ['en' => 'InstaParty', 'ar' => 'إنستا بارتي'],
        'tagline' => ['en' => 'Plan your party', 'ar' => 'خطط لحفلتك'],
        'address_line' => ['en' => 'Cairo, Egypt', 'ar' => 'القاهرة، مصر'],
        'support_email' => 'support@instaparty.app',
        'social' => ['instagram' => 'https://instagram.com/instaparty'],
    ]);

    Cache::forget(GetBrandingAction::CACHE_KEY.':en');
    Cache::forget(GetBrandingAction::CACHE_KEY.':ar');

    getJson('/api/v1/theme/branding', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.site_name', 'InstaParty')
        ->assertJsonPath('data.tagline', 'Plan your party');

    getJson('/api/v1/theme/branding', ['Accept-Language' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.site_name', 'إنستا بارتي')
        ->assertJsonPath('data.tagline', 'خطط لحفلتك');
})->group('shared', 'branding');

it('save action busts cache for both locales', function (): void {
    Cache::put(GetBrandingAction::CACHE_KEY.':en', ['stale'], 60);
    Cache::put(GetBrandingAction::CACHE_KEY.':ar', ['stale'], 60);

    app(SaveBrandingAction::class)->execute([
        'site_name' => ['en' => 'X', 'ar' => 'س'],
    ]);

    expect(Cache::has(GetBrandingAction::CACHE_KEY.':en'))->toBeFalse();
    expect(Cache::has(GetBrandingAction::CACHE_KEY.':ar'))->toBeFalse();
})->group('shared', 'branding');
