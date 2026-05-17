<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\FreezeBookingChatAction;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Events\BookingChatFrozen;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\SubmittedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake();
    $this->mock(NotificationDispatcher::class);
    $this->mock(FirestoreChatGateway::class);
});

function makeFreezeBooking(): Booking
{
    return Booking::factory()->create([
        'lifecycle_status' => SubmittedState::class,
        'payment_status'   => UnpaidState::class,
    ]);
}

function insertChatThread(Booking $booking, array $overrides = []): object
{
    $vendorProfileId = VendorProfile::factory()->approved()->create()->id;

    $id = DB::table('chat_threads')->insertGetId(array_merge([
        'public_id'           => Str::ulid()->toBase32(),
        'firestore_thread_id' => 'fs-thread-'.Str::random(8),
        'customer_id'         => $booking->customer_id,
        'vendor_profile_id'   => $vendorProfileId,
        'booking_id'          => $booking->id,
        'status'              => 'open',
        'locked_at'           => null,
        'frozen_at'           => null,
        'frozen_by'           => null,
        'created_at'          => now(),
        'updated_at'          => now(),
    ], $overrides));

    return DB::table('chat_threads')->where('id', $id)->first();
}

it('sets frozen_at, frozen_by, status to locked and creates intervention + audit log on happy path', function (): void {
    $booking = makeFreezeBooking();
    insertChatThread($booking);
    $adminId = 1;

    $intervention = app(FreezeBookingChatAction::class)
        ->execute($booking->id, $adminId, 'Suspicious activity detected.');

    expect($intervention)->toBeInstanceOf(BookingAdminIntervention::class);
    expect($intervention->intervention_type)->toBe(InterventionType::ChatFrozen);

    $thread = DB::table('chat_threads')->where('booking_id', $booking->id)->first();
    expect($thread->status)->toBe('locked');
    expect($thread->frozen_at)->not->toBeNull();
    expect((int) $thread->frozen_by)->toBe($adminId);

    $this->assertDatabaseHas('booking_admin_interventions', [
        'booking_id'        => $booking->id,
        'admin_id'          => $adminId,
        'intervention_type' => 'chat_frozen',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Booking::class,
        'auditable_id'   => $booking->id,
        'user_id'        => $adminId,
        'action'         => 'booking.chat_frozen',
    ]);
})->group('booking', 'intervention');

it('fires BookingChatFrozen event after commit', function (): void {
    $booking = makeFreezeBooking();
    $thread = insertChatThread($booking);

    app(FreezeBookingChatAction::class)
        ->execute($booking->id, 1, 'Testing event dispatch.');

    Event::assertDispatched(BookingChatFrozen::class, fn (BookingChatFrozen $e): bool =>
        $e->bookingId === $booking->id &&
        $e->firestoreThreadId === $thread->firestore_thread_id &&
        $e->adminId === 1
    );
})->group('booking', 'intervention');

it('throws DomainException when chat thread is already frozen', function (): void {
    $booking = makeFreezeBooking();
    insertChatThread($booking, [
        'status'    => 'locked',
        'frozen_at' => now()->subMinutes(10)->toDateTimeString(),
        'frozen_by' => 1,
        'locked_at' => now()->subMinutes(10)->toDateTimeString(),
    ]);

    app(FreezeBookingChatAction::class)
        ->execute($booking->id, 2, 'Double freeze attempt.');
})->throws(\DomainException::class)->group('booking', 'intervention');

it('throws DomainException when no chat thread exists for the booking', function (): void {
    $booking = makeFreezeBooking();

    app(FreezeBookingChatAction::class)
        ->execute($booking->id, 1, 'No thread exists.');
})->throws(\DomainException::class)->group('booking', 'intervention');
