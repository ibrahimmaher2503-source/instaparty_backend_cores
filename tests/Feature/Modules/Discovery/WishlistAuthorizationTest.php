<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Discovery\Domain\Models\WishlistItem;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Str;

/**
 * P0 — Customer API audit 2026-06-04, finding "Services wishlist routes
 * missing role:customer": a vendor-role token must NOT be able to read or
 * mutate a customer services-wishlist. Vendor wishlist routes (Identity)
 * already enforce role:customer; these tests pin the same contract onto
 * the Discovery services-wishlist routes.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->vendorUser = User::factory()->asVendor()->create();
    $this->service = Service::factory()->create(['status' => ServiceStatus::Published->value]);
});

it('vendor token cannot list services wishlist', function (): void {
    $this->actingAs($this->vendorUser)
        ->getJson('/api/v1/customer/wishlist')
        ->assertStatus(403);
})->group('discovery', 'wishlist', 'authorization');

it('vendor token cannot add to services wishlist', function (): void {
    $this->actingAs($this->vendorUser)
        ->postJson('/api/v1/customer/wishlist/items', ['service_id' => $this->service->public_id])
        ->assertStatus(403);

    // Scoped to this user — global counts are polluted by development seeders.
    expect(Wishlist::where('user_id', $this->vendorUser->id)->exists())->toBeFalse();
})->group('discovery', 'wishlist', 'authorization');

it('vendor token cannot remove from services wishlist', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $wishlist = Wishlist::create(['user_id' => $customer->id, 'public_id' => Str::ulid(), 'name' => 'Default']);
    WishlistItem::create(['wishlist_id' => $wishlist->id, 'service_id' => $this->service->id]);

    $this->actingAs($this->vendorUser)
        ->deleteJson("/api/v1/customer/wishlist/items/{$this->service->public_id}")
        ->assertStatus(403);

    // Scoped to the owning customer's wishlist — global counts are polluted
    // by development seeders.
    expect(WishlistItem::where('wishlist_id', $wishlist->id)->count())->toBe(1);
})->group('discovery', 'wishlist', 'authorization');
