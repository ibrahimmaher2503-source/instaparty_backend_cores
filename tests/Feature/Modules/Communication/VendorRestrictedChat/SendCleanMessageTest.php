<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Communication\Application\Actions\SendVendorChatMessageAction;
use App\Modules\Communication\Application\DTOs\SendVendorChatMessageDTO;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ── Shared setup helper ──────────────────────────────────────────────────────

/**
 * Build a fully-wired vendor + booking + thread ready for SendVendorChatMessageAction.
 *
 * Returns an array-object with the assembled entities so each test can
 * destructure only what it needs.
 *
 * @return array{user: User, vendorProfile: VendorProfile, booking: Booking, bookingVendor: BookingVendor, thread: ChatThread}
 */
function makeVendorChatSetup(): array
{
    $user          = User::factory()->phoneVerified()->asVendor()->create();
    $vendorProfile = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    $customer = User::factory()->phoneVerified()->asCustomer()->create();

    // Booking in VendorReview state — the only lifecycle window where ChatPanelState::Open resolves.
    $booking = Booking::factory()->create(['lifecycle_status' => VendorReviewState::class]);

    $bookingVendor = BookingVendor::factory()->create([
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

    return compact('user', 'vendorProfile', 'booking', 'bookingVendor', 'thread');
}

// ── Tests ────────────────────────────────────────────────────────────────────

it('creates a chat_message_log row with flagged=false on a clean send', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeVendorChatSetup();

    $dto = new SendVendorChatMessageDTO('Hello, looking forward to the party!');

    app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    $this->assertDatabaseHas('chat_message_log', [
        'chat_thread_id' => $thread->id,
        'sender_id'      => $vendorProfile->user_id,
        'flagged'        => 0,
    ]);
})->group('vendor-chat', 'send-message');

it('writes exactly +1 audit_logs row on clean send with action=chat.message.sent', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeVendorChatSetup();

    $countBefore = DB::table('audit_logs')->count();

    $dto = new SendVendorChatMessageDTO('Everything is confirmed, thank you!');

    app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    $countAfter = DB::table('audit_logs')->count();
    expect($countAfter - $countBefore)->toBe(1);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => ChatThread::class,
        'auditable_id'   => $thread->id,
        'user_id'        => $vendorProfile->user_id,
        'action'         => 'chat.message.sent',
    ]);
})->group('vendor-chat', 'send-message');

it('returns the ChatMessageLog instance', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeVendorChatSetup();

    $dto    = new SendVendorChatMessageDTO('Sounds great, we are all set.');
    $result = app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    expect($result)->toBeInstanceOf(ChatMessageLog::class);
    expect($result->id)->toBeInt()->toBeGreaterThan(0);
})->group('vendor-chat', 'send-message');

it('throws DomainException on duplicate submission within 10 seconds', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeVendorChatSetup();

    $dto = new SendVendorChatMessageDTO('This message will be sent twice.');

    // First call: succeeds and plants the dedup cache key.
    app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    // Second call with identical body within TTL must throw.
    expect(fn () => app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto))
        ->toThrow(\DomainException::class, 'Duplicate message submission detected.');
})->group('vendor-chat', 'send-message');

it('throws DomainException when vendor does not own the thread', function (): void {
    ['thread' => $thread] = makeVendorChatSetup();

    // A completely different vendor — has no ownership of $thread.
    $otherVendorProfile = VendorProfile::factory()->approved()->create();

    $dto = new SendVendorChatMessageDTO('I should not be able to send this.');

    expect(fn () => app(SendVendorChatMessageAction::class)->execute($thread, $otherVendorProfile, $dto))
        ->toThrow(\DomainException::class, 'Vendor does not own this chat thread.');
})->group('vendor-chat', 'send-message');

it('throws DomainException when thread is not open (frozen)', function (): void {
    ['user' => $user, 'vendorProfile' => $vendorProfile, 'booking' => $booking] = makeVendorChatSetup();

    $customer = User::factory()->phoneVerified()->asCustomer()->create();

    // Build a frozen thread that still points to the same booking + vendor.
    $frozenThread = ChatThread::factory()->frozen()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'customer_id'       => $customer->id,
    ]);

    $dto = new SendVendorChatMessageDTO('Trying to send on a frozen thread.');

    expect(fn () => app(SendVendorChatMessageAction::class)->execute($frozenThread, $vendorProfile, $dto))
        ->toThrow(\DomainException::class, 'Chat thread is not open for sending.');
})->group('vendor-chat', 'send-message');
