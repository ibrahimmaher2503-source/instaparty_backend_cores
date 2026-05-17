<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Communication\Application\Services\ChatPanelStateResolver;
use App\Modules\Communication\Domain\Enums\ChatPanelState;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Create a non-terminal booking in VendorReview state with a matching
 * vendorProfile and ChatThread (status='open', not frozen).
 *
 * @return array{booking: Booking, vendorProfile: VendorProfile, thread: ChatThread, bookingVendor: BookingVendor}
 */
function makeOpenSetup(
    VendorSubStatus $subStatus = VendorSubStatus::Pending,
    string $threadStatus = 'open',
): array {
    $vendorProfile = VendorProfile::factory()->approved()->create();

    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);

    $bookingVendor = BookingVendor::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'sub_status'        => $subStatus,
    ]);

    $thread = ChatThread::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'status'            => $threadStatus,
        'frozen_at'         => null,
    ]);

    return compact('booking', 'vendorProfile', 'thread', 'bookingVendor');
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('returns Placeholder when thread is null', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);

    $state = app(ChatPanelStateResolver::class)->resolve(null, $booking);

    expect($state)->toBe(ChatPanelState::Placeholder);
})->group('vendor-chat', 'panel-state');

it('returns Closed when booking lifecycle is completed', function (): void {
    $vendorProfile = VendorProfile::factory()->approved()->create();

    $booking = Booking::factory()->completed()->create();

    $thread = ChatThread::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'status'            => 'open',
        'frozen_at'         => null,
    ]);

    $state = app(ChatPanelStateResolver::class)->resolve($thread, $booking);

    expect($state)->toBe(ChatPanelState::Closed);
})->group('vendor-chat', 'panel-state');

it('returns Closed when booking lifecycle is cancelled', function (): void {
    $vendorProfile = VendorProfile::factory()->approved()->create();

    $booking = Booking::factory()->cancelled()->create();

    $thread = ChatThread::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'status'            => 'open',
        'frozen_at'         => null,
    ]);

    $state = app(ChatPanelStateResolver::class)->resolve($thread, $booking);

    expect($state)->toBe(ChatPanelState::Closed);
})->group('vendor-chat', 'panel-state');

it('returns Frozen when thread has frozen_at set', function (): void {
    $vendorProfile = VendorProfile::factory()->approved()->create();

    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);

    // frozen() factory state sets status='locked' AND frozen_at=now()
    $thread = ChatThread::factory()->frozen()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
    ]);

    $state = app(ChatPanelStateResolver::class)->resolve($thread, $booking);

    expect($state)->toBe(ChatPanelState::Frozen);
})->group('vendor-chat', 'panel-state');

it('returns SystemLocked when thread status is locked and frozen_at is null', function (): void {
    ['booking' => $booking, 'vendorProfile' => $vendorProfile] = makeOpenSetup(
        subStatus: VendorSubStatus::Pending,
        threadStatus: 'locked',
    );

    // Manually ensure frozen_at is null (locked lifecycle, not admin-frozen)
    $thread = ChatThread::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'status'            => 'locked',
        'frozen_at'         => null,
    ]);

    BookingVendor::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'sub_status'        => VendorSubStatus::Pending,
    ]);

    $state = app(ChatPanelStateResolver::class)->resolve($thread, $booking);

    expect($state)->toBe(ChatPanelState::SystemLocked);
})->group('vendor-chat', 'panel-state');

it('returns SystemLocked when bookingVendor sub_status is Accepted', function (): void {
    ['booking' => $booking, 'vendorProfile' => $vendorProfile, 'thread' => $thread]
        = makeOpenSetup(subStatus: VendorSubStatus::Accepted);

    $state = app(ChatPanelStateResolver::class)->resolve($thread, $booking);

    expect($state)->toBe(ChatPanelState::SystemLocked);
})->group('vendor-chat', 'panel-state');

it('returns Open when thread is open and bookingVendor sub_status is Pending', function (): void {
    ['booking' => $booking, 'thread' => $thread]
        = makeOpenSetup(subStatus: VendorSubStatus::Pending);

    $state = app(ChatPanelStateResolver::class)->resolve($thread, $booking);

    expect($state)->toBe(ChatPanelState::Open);
})->group('vendor-chat', 'panel-state');

it('returns Open when bookingVendor sub_status is Modified', function (): void {
    ['booking' => $booking, 'thread' => $thread]
        = makeOpenSetup(subStatus: VendorSubStatus::Modified);

    $state = app(ChatPanelStateResolver::class)->resolve($thread, $booking);

    expect($state)->toBe(ChatPanelState::Open);
})->group('vendor-chat', 'panel-state');

it('returns Placeholder when thread exists but no matching bookingVendor', function (): void {
    // Thread belongs to vendorProfileA, but no BookingVendor row ties that
    // vendor to the booking — simulated by using a different vendor_profile_id
    // on the thread than the one that has the BookingVendor row.
    $vendorProfileA = VendorProfile::factory()->approved()->create();
    $vendorProfileB = VendorProfile::factory()->approved()->create();

    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);

    // BookingVendor exists for vendorProfileB only
    BookingVendor::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfileB->id,
        'sub_status'        => VendorSubStatus::Pending,
    ]);

    // Thread points to vendorProfileA — no matching BookingVendor row
    $thread = ChatThread::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfileA->id,
        'status'            => 'open',
        'frozen_at'         => null,
    ]);

    $state = app(ChatPanelStateResolver::class)->resolve($thread, $booking);

    expect($state)->toBe(ChatPanelState::Placeholder);
})->group('vendor-chat', 'panel-state');

it('returns Closed as default when thread status is closed, sub_status is Pending, and booking is not terminal', function (): void {
    $vendorProfile = VendorProfile::factory()->approved()->create();

    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
    ]);

    BookingVendor::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'sub_status'        => VendorSubStatus::Pending,
    ]);

    // Thread is status='closed', not locked, not frozen — falls through to default
    $thread = ChatThread::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'status'            => 'closed',
        'frozen_at'         => null,
        'locked_at'         => null,
    ]);

    $state = app(ChatPanelStateResolver::class)->resolve($thread, $booking);

    expect($state)->toBe(ChatPanelState::Closed);
})->group('vendor-chat', 'panel-state');
