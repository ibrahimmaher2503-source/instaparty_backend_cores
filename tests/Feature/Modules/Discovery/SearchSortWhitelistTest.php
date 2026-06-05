<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;

use function Pest\Laravel\getJson;

uses()->group('discovery', 'search', 'sort');

it('accepts sort=price_asc', function (): void {
    getJson('/api/v1/customer/services?sort=price_asc')
        ->assertOk();
});

it('accepts sort=price_desc', function (): void {
    getJson('/api/v1/customer/services?sort=price_desc')
        ->assertOk();
});

it('accepts sort=rating_desc', function (): void {
    getJson('/api/v1/customer/services?sort=rating_desc')
        ->assertOk();
});

it('accepts sort=newest', function (): void {
    getJson('/api/v1/customer/services?sort=newest')
        ->assertOk();
});

it('rejects unknown sort value with 422', function (): void {
    getJson('/api/v1/customer/services?sort=random')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sort']);
});

it('rejects legacy sort=rating value with 422', function (): void {
    getJson('/api/v1/customer/services?sort=rating')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sort']);
});

it('sort param is optional - no param returns 200', function (): void {
    getJson('/api/v1/customer/services')
        ->assertOk();
});

it('sort can be combined with type filter', function (): void {
    getJson('/api/v1/customer/services?sort=price_asc&product_type=sale')
        ->assertOk();
});

// ─────────────────────────────────────────────────────────────────────────────
// SRCH-001 regression — under scout:database the Scout path used to leak the
// combined sort spec ('price_minor:asc') into SQL as a literal column → 500.
// Non-meilisearch drivers must take the SQL fallback.
// ─────────────────────────────────────────────────────────────────────────────

it('every sort works under the scout database driver (SRCH-001)', function (string $sort): void {
    config(['scout.driver' => 'database']);

    getJson("/api/v1/customer/services?sort={$sort}")
        ->assertOk();
})->with(['price_asc', 'price_desc', 'rating_desc', 'newest']);

it('sort=price_asc returns services cheapest-first', function (): void {
    $expensive = Service::factory()->sale()->create([
        'status' => PublishedState::class,
        'base_price_minor' => 90000,
    ]);
    $cheap = Service::factory()->sale()->create([
        'status' => PublishedState::class,
        'base_price_minor' => 1,
    ]);

    $response = getJson('/api/v1/customer/services?sort=price_asc&per_page=50')->assertOk();

    $ids = collect($response->json('data'))->pluck('public_id');
    expect($ids->search($cheap->public_id))->toBeLessThan($ids->search($expensive->public_id));
});
