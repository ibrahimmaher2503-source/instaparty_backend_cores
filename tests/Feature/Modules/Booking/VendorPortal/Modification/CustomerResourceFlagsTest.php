<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Resources\BookingItemResource;
use App\Modules\Booking\Http\Resources\BookingResource;
use App\Modules\Booking\Http\Resources\BookingVendorResource;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/../../BookingTestHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
});

it('flags requires_customer_approval=true on BookingResource when a pending modification exists', function (): void {
    $data = makeBookingWithPendingModification();
    $this->actingAs($data['customer']);

    $payload = (new BookingResource($data['booking']->fresh()))->toArray(Request::create('/', 'GET'));

    expect($payload['requires_customer_approval'])->toBeTrue();
})->group('booking', 'modification-builder', 'i18n');

it('flags is_modified=true and populates change_badges on the affected BookingItemResource', function (): void {
    $data = makeBookingWithPendingModification();

    // Update the item's payload to include a change_type so the badge is populated
    $modItem = $data['modification']->items()->first();
    $modItem->update([
        'payload' => array_merge($modItem->payload, ['change_type' => 'change_price']),
    ]);

    $this->actingAs($data['customer']);

    $payload = (new BookingItemResource($data['item']->fresh()))
        ->toArray(Request::create('/', 'GET'));

    expect($payload['is_modified'])->toBeTrue();
    expect($payload['change_badges'])->toContain('change_price');
})->group('booking', 'modification-builder');

it('returns active_modification_proposal on BookingVendorResource when pending exists', function (): void {
    $data = makeBookingWithPendingModification();
    $this->actingAs($data['customer']);

    $payload = (new BookingVendorResource($data['bookingVendor']->fresh()))
        ->toArray(Request::create('/', 'GET'));

    expect($payload['active_modification_proposal'])->not->toBeNull();
    expect($payload['active_modification_proposal']['proposal_kind'])->toBe('change_price');
    expect($payload['active_modification_proposal']['change_count'])->toBe(1);
})->group('booking', 'modification-builder', 'i18n');

it('returns null active_modification_proposal when no pending modification exists', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $this->actingAs($data['customer']);

    $payload = (new BookingVendorResource($data['bookingVendor']->fresh()))
        ->toArray(Request::create('/', 'GET'));

    expect($payload['active_modification_proposal'])->toBeNull();
})->group('booking', 'modification-builder');
