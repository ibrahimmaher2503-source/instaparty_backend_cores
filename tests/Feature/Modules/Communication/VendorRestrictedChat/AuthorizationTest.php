<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Communication\Application\Actions\SendVendorChatMessageAction;
use App\Modules\Communication\Application\DTOs\SendVendorChatMessageDTO;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Shared helper ─────────────────────────────────────────────────────────────

/**
 * Build the minimal context for authorization tests:
 *  - one vendor user + approved VendorProfile
 *  - a non-terminal booking in VendorReview state
 *  - a matching BookingVendor with the given sub_status
 *  - an open, non-frozen ChatThread tied to that booking and vendor
 *
 * @return array{user: User, vendorProfile: VendorProfile, booking: Booking, bookingVendor: BookingVendor, thread: ChatThread}
 */
function makeAuthSetup(
    VendorSubStatus $subStatus = VendorSubStatus::Pending,
): array {
    $user          = User::factory()->phoneVerified()->asVendor()->create();
    $vendorProfile = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    $customer = User::factory()->phoneVerified()->asCustomer()->create();

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
        'customer_id'       => $customer->id,
        'status'            => 'open',
        'frozen_at'         => null,
    ]);

    return compact('user', 'vendorProfile', 'booking', 'bookingVendor', 'thread');
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('throws DomainException when vendor does not own the thread', function (): void {
    ['thread' => $thread] = makeAuthSetup();

    // A completely independent vendor — unrelated to the thread's vendor_profile_id.
    $intruder = VendorProfile::factory()->approved()->create();

    expect(
        fn () => app(SendVendorChatMessageAction::class)
            ->execute($thread, $intruder, new SendVendorChatMessageDTO('Hello'))
    )->toThrow(\DomainException::class, 'Vendor does not own this chat thread.');
})->group('vendor-chat', 'authorization');

it('throws DomainException when thread is frozen', function (): void {
    ['vendorProfile' => $vendorProfile, 'booking' => $booking] = makeAuthSetup();

    $customer = User::factory()->phoneVerified()->asCustomer()->create();

    // Replace the open thread with a frozen one pointing to the same booking + vendor.
    $frozenThread = ChatThread::factory()->frozen()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'customer_id'       => $customer->id,
    ]);

    expect(
        fn () => app(SendVendorChatMessageAction::class)
            ->execute($frozenThread, $vendorProfile, new SendVendorChatMessageDTO('Hello'))
    )->toThrow(\DomainException::class, 'Chat thread is not open for sending.');
})->group('vendor-chat', 'authorization');

it('throws DomainException when bookingVendor sub_status is Accepted (outside review window)', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeAuthSetup(
        subStatus: VendorSubStatus::Accepted,
    );

    expect(
        fn () => app(SendVendorChatMessageAction::class)
            ->execute($thread, $vendorProfile, new SendVendorChatMessageDTO('Hello'))
    )->toThrow(\DomainException::class, 'Chat thread is not open for sending.');
})->group('vendor-chat', 'authorization');

it('throws DomainException when booking lifecycle is completed (terminal)', function (): void {
    $user          = User::factory()->phoneVerified()->asVendor()->create();
    $vendorProfile = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $customer      = User::factory()->phoneVerified()->asCustomer()->create();

    // Terminal booking — CompletedState
    $booking = Booking::factory()->completed()->create();

    BookingVendor::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'sub_status'        => VendorSubStatus::Pending,
    ]);

    $thread = ChatThread::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'customer_id'       => $customer->id,
        'status'            => 'open',
        'frozen_at'         => null,
    ]);

    expect(
        fn () => app(SendVendorChatMessageAction::class)
            ->execute($thread, $vendorProfile, new SendVendorChatMessageDTO('Hello'))
    )->toThrow(\DomainException::class, 'Chat thread is not open for sending.');
})->group('vendor-chat', 'authorization');
