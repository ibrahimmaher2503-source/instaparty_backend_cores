<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Communication\Application\Actions\SendVendorChatMessageAction;
use App\Modules\Communication\Application\DTOs\SendVendorChatMessageDTO;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Events\ChatFlagged;
use App\Modules\Communication\Domain\Events\ChatMessageFlagged;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

// ── Shared setup helper ──────────────────────────────────────────────────────

/**
 * Build a fully-wired vendor + booking + thread ready for SendVendorChatMessageAction.
 *
 * @return array{vendorProfile: VendorProfile, booking: Booking, thread: ChatThread}
 */
function makeBlockedChatSetup(): array
{
    $user          = User::factory()->phoneVerified()->asVendor()->create();
    $vendorProfile = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    $customer = User::factory()->phoneVerified()->asCustomer()->create();

    $booking = Booking::factory()->create(['lifecycle_status' => VendorReviewState::class]);

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

    return compact('vendorProfile', 'booking', 'thread');
}

// ── Tests ────────────────────────────────────────────────────────────────────

it('marks the ChatMessageLog as flagged when body contains a phone number', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeBlockedChatSetup();

    $dto = new SendVendorChatMessageDTO('Call me on 01012345678');

    $log = app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    $this->assertDatabaseHas('chat_message_log', [
        'id'      => $log->id,
        'flagged' => 1,
    ]);
})->group('vendor-chat', 'blocked-message');

it('creates a ChatModerationFlag row with flag_type=phone and action_taken=block', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeBlockedChatSetup();

    $dto = new SendVendorChatMessageDTO('Call me on 01012345678');

    $log = app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    $this->assertDatabaseHas('chat_moderation_flags', [
        'chat_message_log_id' => $log->id,
        'flag_type'           => ChatFlagType::Phone->value,
        'action_taken'        => ChatFlagAction::Block->value,
    ]);
})->group('vendor-chat', 'blocked-message');

it('does NOT dispatch the gateway sendMessage for blocked messages', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeBlockedChatSetup();

    // Bind a mock that explicitly forbids sendMessage for blocked content.
    $this->instance(
        FirestoreChatGateway::class,
        Mockery::mock(FirestoreChatGateway::class, function ($mock): void {
            $mock->shouldNotReceive('sendMessage');
            $mock->shouldReceive('freezeThread')->andReturnNull()->zeroOrMoreTimes();
            $mock->shouldReceive('unfreezeThread')->andReturnNull()->zeroOrMoreTimes();
        }),
    );

    $dto = new SendVendorChatMessageDTO('Call me on 01012345678');

    app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    // Mockery verifies shouldNotReceive automatically on teardown.
    // Explicit check: no assertion exception means gateway was NOT called.
    expect(true)->toBeTrue();
})->group('vendor-chat', 'blocked-message');

it('dispatches ChatFlagged event after commit for blocked message', function (): void {
    Event::fake([ChatFlagged::class]);

    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeBlockedChatSetup();

    $dto = new SendVendorChatMessageDTO('Call me on 01012345678');

    app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    Event::assertDispatched(
        ChatFlagged::class,
        fn (ChatFlagged $e): bool => $e->chatThreadId === $thread->id,
    );
    Event::assertDispatchedTimes(ChatFlagged::class, 1);
})->group('vendor-chat', 'blocked-message');

it('dispatches ChatMessageFlagged event after commit for blocked message', function (): void {
    Event::fake([ChatMessageFlagged::class]);

    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeBlockedChatSetup();

    $dto = new SendVendorChatMessageDTO('Call me on 01012345678');

    $log = app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    Event::assertDispatched(
        ChatMessageFlagged::class,
        fn (ChatMessageFlagged $e): bool => $e->log->is($log),
    );
    Event::assertDispatchedTimes(ChatMessageFlagged::class, 1);
})->group('vendor-chat', 'blocked-message');

it('writes audit_logs row with action=chat.message.blocked', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeBlockedChatSetup();

    $countBefore = DB::table('audit_logs')->count();

    $dto = new SendVendorChatMessageDTO('Call me on 01012345678');

    app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    $countAfter = DB::table('audit_logs')->count();
    expect($countAfter - $countBefore)->toBe(1);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => ChatThread::class,
        'auditable_id'   => $thread->id,
        'user_id'        => $vendorProfile->user_id,
        'action'         => 'chat.message.blocked',
    ]);
})->group('vendor-chat', 'blocked-message');

it('handles email pattern: marks flagged, creates flag with flag_type=email', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeBlockedChatSetup();

    $dto = new SendVendorChatMessageDTO('Email me at user@example.com');

    $log = app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    $this->assertDatabaseHas('chat_message_log', [
        'id'      => $log->id,
        'flagged' => 1,
    ]);

    $this->assertDatabaseHas('chat_moderation_flags', [
        'chat_message_log_id' => $log->id,
        'flag_type'           => ChatFlagType::Email->value,
        'action_taken'        => ChatFlagAction::Block->value,
    ]);
})->group('vendor-chat', 'blocked-message');

it('handles external_link pattern: marks flagged, creates flag with flag_type=external_link', function (): void {
    ['vendorProfile' => $vendorProfile, 'thread' => $thread] = makeBlockedChatSetup();

    // instaparty.eg is excluded by the detector — use a genuinely external URL.
    $dto = new SendVendorChatMessageDTO('Visit https://example.com for details');

    $log = app(SendVendorChatMessageAction::class)->execute($thread, $vendorProfile, $dto);

    $this->assertDatabaseHas('chat_message_log', [
        'id'      => $log->id,
        'flagged' => 1,
    ]);

    $this->assertDatabaseHas('chat_moderation_flags', [
        'chat_message_log_id' => $log->id,
        'flag_type'           => ChatFlagType::ExternalLink->value,
        'action_taken'        => ChatFlagAction::Block->value,
    ]);
})->group('vendor-chat', 'blocked-message');
