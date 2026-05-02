<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Discovery\Domain\Models\WishlistItem;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');
    $this->service = Service::factory()->create(['status' => ServiceStatus::Published]);
});

it('unauthenticated request to add wishlist returns 401', function (): void {
    $this->postJson('/api/v1/customer/wishlist/items', ['service_id' => $this->service->public_id])
        ->assertStatus(401);
})->group('discovery', 'wishlist');

it('unauthenticated request to list wishlist returns 401', function (): void {
    $this->getJson('/api/v1/customer/wishlist')
        ->assertStatus(401);
})->group('discovery', 'wishlist');

it('authenticated customer can add a service to wishlist', function (): void {
    $this->actingAs($this->customer)
        ->postJson('/api/v1/customer/wishlist/items', ['service_id' => $this->service->public_id])
        ->assertStatus(201)
        ->assertJsonPath('data.service_id', $this->service->public_id);

    expect(WishlistItem::count())->toBe(1);
})->group('discovery', 'wishlist');

it('adding same service twice is idempotent', function (): void {
    $this->actingAs($this->customer)
        ->postJson('/api/v1/customer/wishlist/items', ['service_id' => $this->service->public_id]);
    $this->actingAs($this->customer)
        ->postJson('/api/v1/customer/wishlist/items', ['service_id' => $this->service->public_id]);

    expect(WishlistItem::count())->toBe(1);
})->group('discovery', 'wishlist');

it('authenticated customer can remove a service from wishlist', function (): void {
    $wishlist = Wishlist::create(['user_id' => $this->customer->id, 'public_id' => Str::ulid(), 'name' => 'Default']);
    WishlistItem::create(['wishlist_id' => $wishlist->id, 'service_id' => $this->service->id]);

    $this->actingAs($this->customer)
        ->deleteJson("/api/v1/customer/wishlist/items/{$this->service->public_id}")
        ->assertStatus(204);

    expect(WishlistItem::count())->toBe(0);
})->group('discovery', 'wishlist');

it('customer can list wishlist items', function (): void {
    $wishlist = Wishlist::create(['user_id' => $this->customer->id, 'public_id' => Str::ulid(), 'name' => 'Default']);
    WishlistItem::create(['wishlist_id' => $wishlist->id, 'service_id' => $this->service->id]);

    $this->actingAs($this->customer)
        ->getJson('/api/v1/customer/wishlist')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
})->group('discovery', 'wishlist');

it('adding non-published service returns 404', function (): void {
    $draft = Service::factory()->create(['status' => ServiceStatus::Draft]);
    $this->actingAs($this->customer)
        ->postJson('/api/v1/customer/wishlist/items', ['service_id' => $draft->public_id])
        ->assertStatus(404);
})->group('discovery', 'wishlist');
