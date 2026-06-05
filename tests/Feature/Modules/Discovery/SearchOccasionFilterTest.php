<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Gap-closure Phase 2.1 — canonical `occasion` param on GET /customer/services.
 * `occasion` is canonical; `occasion_slug` is the back-compat alias. Both match
 * occasions.code (OccasionResource exposes `code` as `slug`).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->occasion = Occasion::factory()->create();

    $this->matching = Service::factory()->rental()->published()->create();
    DB::table('occasion_category')->insert([
        'occasion_id' => $this->occasion->id,
        'category_id' => $this->matching->category_id,
    ]);

    $this->nonMatching = Service::factory()->sale()->published()->create();
});

it('filters services by the canonical occasion param', function (): void {
    $response = $this->getJson('/api/v1/customer/services?occasion='.$this->occasion->code)
        ->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('public_id');

    expect($ids)->toContain($this->matching->public_id)
        ->and($ids)->not->toContain($this->nonMatching->public_id)
        ->and($response->json('meta.filters_applied.occasion'))->toBe($this->occasion->code);
})->group('discovery', 'search');

it('still honors the occasion_slug back-compat alias', function (): void {
    $response = $this->getJson('/api/v1/customer/services?occasion_slug='.$this->occasion->code)
        ->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('public_id');

    expect($ids)->toContain($this->matching->public_id)
        ->and($ids)->not->toContain($this->nonMatching->public_id);
})->group('discovery', 'search');

it('prefers the canonical occasion param when both are sent', function (): void {
    $other = Occasion::factory()->create();

    $response = $this->getJson(
        '/api/v1/customer/services?occasion='.$this->occasion->code.'&occasion_slug='.$other->code
    )->assertStatus(200);

    expect($response->json('meta.filters_applied.occasion'))->toBe($this->occasion->code);
})->group('discovery', 'search');

it('returns an empty result set for an unknown occasion instead of erroring', function (): void {
    $response = $this->getJson('/api/v1/customer/services?occasion=does-not-exist')
        ->assertStatus(200);

    expect($response->json('data'))->toBe([]);
})->group('discovery', 'search');
