<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Actions\GetPublicFeatureFlagsAction;
use App\Modules\Shared\Domain\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    Cache::forget(GetPublicFeatureFlagsAction::CACHE_KEY);
    FeatureFlag::query()->delete();
});

it('returns only frontend.* flags', function (): void {
    FeatureFlag::create(['key' => 'frontend.experimental_wizard', 'is_enabled' => true, 'rollout_pct' => 50]);
    FeatureFlag::create(['key' => 'frontend.dark_mode', 'is_enabled' => false, 'rollout_pct' => 0]);
    FeatureFlag::create(['key' => 'financial_ledger_hardening_v2', 'is_enabled' => true, 'rollout_pct' => 100]);

    Cache::forget(GetPublicFeatureFlagsAction::CACHE_KEY);

    $response = getJson('/api/v1/feature-flags/public');
    $response->assertOk();

    $keys = collect($response->json('data'))->pluck('key')->all();

    expect($keys)->toContain('frontend.experimental_wizard');
    expect($keys)->toContain('frontend.dark_mode');
    expect($keys)->not->toContain('financial_ledger_hardening_v2');
})->group('shared', 'feature-flags');

it('returns empty list when no frontend.* flags exist', function (): void {
    FeatureFlag::create(['key' => 'internal.thing', 'is_enabled' => true, 'rollout_pct' => 100]);

    getJson('/api/v1/feature-flags/public')
        ->assertOk()
        ->assertJsonPath('data', []);
})->group('shared', 'feature-flags');
