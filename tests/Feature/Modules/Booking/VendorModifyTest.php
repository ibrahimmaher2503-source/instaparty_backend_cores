<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('vendor modifies → sub_status becomes modified', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $response = $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind'      => 'change_price',
            'vendor_explanation' => ['en' => 'Updated pricing', 'ar' => 'تسعير محدث'],
            'changes'            => [
                [
                    'change_kind'           => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload'               => ['unit_price_minor' => 60000],
                ],
            ],
        ]
    );

    $response->assertCreated();
    $bv->refresh();
    expect($bv->sub_status)->toBe(VendorSubStatus::Modified);
    expect($bv->responded_at)->not->toBeNull();
})->group('booking', 'negotiation');

it('vendor modifies → booking lifecycle_status becomes customer_review', function (): void {
    ['vendor' => $vendor, 'booking' => $booking, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind' => 'change_price',
            'changes'       => [
                [
                    'change_kind'           => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload'               => ['unit_price_minor' => 60000],
                ],
            ],
        ]
    )->assertCreated();

    $booking->refresh();
    expect($booking->lifecycle_status)->toBe(LifecycleStatus::CustomerReview);
})->group('booking', 'negotiation');

it('modification has diff_snapshot with before and after', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind' => 'change_price',
            'changes'       => [
                [
                    'change_kind'           => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload'               => ['unit_price_minor' => 75000],
                ],
            ],
        ]
    )->assertCreated();

    $modification = BookingModification::where('booking_vendor_id', $bv->id)->first();
    expect($modification)->not->toBeNull();

    $diff = $modification->diff_snapshot;
    expect($diff)->toHaveKeys(['before', 'after']);
    expect($diff['before']['subtotal_minor'])->toBe(50000);
    expect($diff['after']['subtotal_minor'])->toBe(75000);
})->group('booking', 'negotiation');

it('booking_state_transitions has row to modified after vendor modifies', function (): void {
    ['vendor' => $vendor, 'booking' => $booking, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind' => 'change_price',
            'changes'       => [
                [
                    'change_kind'           => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload'               => ['unit_price_minor' => 60000],
                ],
            ],
        ]
    );

    expect(\Illuminate\Support\Facades\DB::table('booking_state_transitions')
        ->where('transitionable_type', BookingVendor::class)
        ->where('transitionable_id', $bv->id)
        ->where('to_state', 'modified')
        ->exists()
    )->toBeTrue();

    expect(\Illuminate\Support\Facades\DB::table('booking_state_transitions')
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->where('to_state', 'customer_review')
        ->exists()
    )->toBeTrue();
})->group('booking', 'negotiation');

it('returns 409 when a pending modification already exists', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $payload = [
        'proposal_kind' => 'change_price',
        'changes'       => [
            [
                'change_kind'           => 'update',
                'target_item_public_id' => $item->public_id,
                'payload'               => ['unit_price_minor' => 60000],
            ],
        ],
    ];

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        $payload
    )->assertCreated();

    // sub_status is now modified; reset to pending to test pending-modification guard
    $bv->update(['sub_status' => VendorSubStatus::Pending]);

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        $payload
    )->assertStatus(409);
})->group('booking', 'negotiation');

it('returns 403 when wrong vendor tries to modify', function (): void {
    ['bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();
    $otherVendor = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->approved()->create();

    $this->actingAs($otherVendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind' => 'change_price',
            'changes'       => [
                [
                    'change_kind'           => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload'               => ['unit_price_minor' => 60000],
                ],
            ],
        ]
    )->assertStatus(403);
})->group('booking', 'negotiation');

it('returns 409 when vendor modifies non-pending booking_vendor', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();
    $bv->update(['sub_status' => VendorSubStatus::Accepted]);

    $this->actingAs($vendor->user)->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind' => 'change_price',
            'changes'       => [
                [
                    'change_kind'           => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload'               => ['unit_price_minor' => 60000],
                ],
            ],
        ]
    )->assertStatus(409);
})->group('booking', 'negotiation');

it('returns 401 when unauthenticated on modify', function (): void {
    ['bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $this->postJson(
        "/api/v1/vendor/booking-vendors/{$bv->public_id}/modify",
        [
            'proposal_kind' => 'change_price',
            'changes'       => [
                [
                    'change_kind'           => 'update',
                    'target_item_public_id' => $item->public_id,
                    'payload'               => ['unit_price_minor' => 60000],
                ],
            ],
        ]
    )->assertStatus(401);
})->group('booking', 'negotiation');
