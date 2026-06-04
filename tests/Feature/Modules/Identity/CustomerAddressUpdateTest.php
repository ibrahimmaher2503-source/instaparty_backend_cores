<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;

/**
 * Phase 3 D — 3.3 PATCH address + 3.5 set-default.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->customer = User::factory()->asCustomer()->phoneVerified()->create();
});

it('updates an own address partially', function (): void {
    $address = CustomerAddress::factory()->create(['user_id' => $this->customer->id, 'address_line' => 'Old St 1']);

    $this->actingAs($this->customer)
        ->patchJson("/api/v1/customer/addresses/{$address->public_id}", ['address_line' => 'New St 2'])
        ->assertStatus(200)
        ->assertJsonPath('data.address_line', 'New St 2');

    expect($address->refresh()->address_line)->toBe('New St 2');
})->group('identity', 'addresses');

it('set-default clears the flag on other addresses', function (): void {
    $a = CustomerAddress::factory()->create(['user_id' => $this->customer->id, 'is_default' => true]);
    $b = CustomerAddress::factory()->create(['user_id' => $this->customer->id, 'is_default' => false]);

    $this->actingAs($this->customer)
        ->postJson("/api/v1/customer/addresses/{$b->public_id}/set-default")
        ->assertStatus(200)
        ->assertJsonPath('data.is_default', true);

    expect((bool) $a->refresh()->is_default)->toBeFalse()
        ->and((bool) $b->refresh()->is_default)->toBeTrue();
})->group('identity', 'addresses');

it('cannot update or set-default another customer address — 404', function (): void {
    $other = User::factory()->asCustomer()->create();
    $foreign = CustomerAddress::factory()->create(['user_id' => $other->id]);

    $this->actingAs($this->customer)
        ->patchJson("/api/v1/customer/addresses/{$foreign->public_id}", ['address_line' => 'X'])
        ->assertStatus(404);

    $this->actingAs($this->customer)
        ->postJson("/api/v1/customer/addresses/{$foreign->public_id}/set-default")
        ->assertStatus(404);
})->group('identity', 'addresses', 'privacy');

it('validates field bounds on update', function (): void {
    $address = CustomerAddress::factory()->create(['user_id' => $this->customer->id]);

    $this->actingAs($this->customer)
        ->patchJson("/api/v1/customer/addresses/{$address->public_id}", ['city_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['city_id']);
})->group('identity', 'addresses');
