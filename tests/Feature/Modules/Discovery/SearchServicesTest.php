<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;

it('search endpoint is publicly accessible', function (): void {
    $this->getJson('/api/v1/customer/services?q=test')
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta' => ['total', 'current_page', 'per_page', 'last_page']]);
})->group('discovery', 'search');

it('invalid product type returns 422', function (): void {
    $this->getJson('/api/v1/customer/services?type=invalid_type')
        ->assertStatus(422);
})->group('discovery', 'search');

it('per_page exceeding 50 returns 422', function (): void {
    $this->getJson('/api/v1/customer/services?per_page=100')
        ->assertStatus(422);
})->group('discovery', 'search');

it('page out of range returns empty results gracefully', function (): void {
    $this->getJson('/api/v1/customer/services?page=9999')
        ->assertStatus(200)
        ->assertJsonPath('meta.current_page', 9999);
})->group('discovery', 'search');

it('whitespace only query returns results gracefully', function (): void {
    $this->getJson('/api/v1/customer/services?q=   ')
        ->assertStatus(200);
})->group('discovery', 'search');
