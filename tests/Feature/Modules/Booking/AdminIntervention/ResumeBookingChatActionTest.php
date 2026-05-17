<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\ResumeBookingChatAction;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Events\BookingChatResumed;
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

function makeResumeBooking(): Booking
{
    return Booking::factory()->create([
        'lifecycle_status' => SubmittedState::class,
        'payment_status'   => UnpaidState::class,
    ]);
}

function insertFrozenThread(Booking $booking): object
{
    $vendorProfileId = VendorProfile::factory()->approved()->create()->id;
    $frozenAt        = now()->subMinutes(30)->toDateTimeString();

    $id = DB::table('chat_threads')->insertGetId([
        'public_id'           => Str::ulid()->toBase32(),
        'firestore_thread_id' => 'frozen-'.Str::random(8),
        'customer_id'         => $booking->customer_id,
        'vendor_profile_id'   => $vendorProfileId,
        'booking_id'          => $booking->id,
        'status'              => 'locked',
        'locked_at'           => $frozenAt,
        'frozen_at'           => $frozenAt,
        'frozen_by'           => 1,
        'created_at'          => now()->toDateTimeString(),
        'updated_at'          => now()->toDateTimeString(),
    ]);

    return DB::table('chat_threads')->where('id', $id)->first();
}

it('clears frozen_at, frozen_by and sets status to open, creates intervention + audit log on happy path', function (): void {
    $booking = makeResumeBooking();
    insertFrozenThread($booking);
    $adminId = 2;

    $intervention = app(ResumeBookingChatAction::class)
        ->execute($booking->id, $adminId, 'Issue resolved, resuming conversation.');

    expect($intervention)->toBeInstanceOf(BookingAdminIntervention::class);
    expect($intervention->intervention_type)->toBe(InterventionType::ChatResumed);

    $thread = DB::table('chat_threads')->where('booking_id', $booking->id)->first();
    expect($thread->status)->toBe('open');
    expect($thread->frozen_at)->toBeNull();
    expect($thread->frozen_by)->toBeNull();

    $this->assertDatabaseHas('booking_admin_interventions', [
        'booking_id'        => $booking->id,
        'admin_id'          => $adminId,
        'intervention_type' => 'chat_resumed',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Booking::class,
        'auditable_id'   => $booking->id,
        'user_id'        => $adminId,
        'action'         => 'booking.chat_resumed',
    ]);
})->group('booking', 'intervention');

it('fires BookingChatResumed event after commit', function (): void {
    $booking = makeResumeBooking();
    $thread  = insertFrozenThread($booking);

    app(ResumeBookingChatAction::class)
        ->execute($booking->id, 2, 'Resuming after review.');

    Event::assertDispatched(BookingChatResumed::class, fn (BookingChatResumed $e): bool =>
        $e->bookingId === $booking->id &&
        $e->firestoreThreadId === $thread->firestore_thread_id &&
        $e->adminId === 2
    );
})->group('booking', 'intervention');

it('throws DomainException when chat is not currently frozen', function (): void {
    $booking = makeResumeBooking();

    DB::table('chat_threads')->insert([
        'public_id'           => Str::ulid()->toBase32(),
        'firestore_thread_id' => 'open-'.Str::random(8),
        'customer_id'         => $booking->customer_id,
        'vendor_profile_id'   => VendorProfile::factory()->approved()->create()->id,
        'booking_id'          => $booking->id,
        'status'              => 'open',
        'locked_at'           => null,
        'frozen_at'           => null,
        'frozen_by'           => null,
        'created_at'          => now()->toDateTimeString(),
        'updated_at'          => now()->toDateTimeString(),
    ]);

    app(ResumeBookingChatAction::class)
        ->execute($booking->id, 1, 'Trying to resume non-frozen chat.');
})->throws(\DomainException::class)->group('booking', 'intervention');

it('throws DomainException when no chat thread exists for the booking', function (): void {
    $booking = makeResumeBooking();

    app(ResumeBookingChatAction::class)
        ->execute($booking->id, 1, 'No thread exists.');
})->throws(\DomainException::class)->group('booking', 'intervention');
