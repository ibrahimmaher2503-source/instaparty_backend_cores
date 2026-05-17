<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\CreateAdminInterventionNoteAction;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Events\BookingChatFrozen;
use App\Modules\Booking\Domain\Events\BookingChatResumed;
use App\Modules\Booking\Domain\Events\BookingForceCancelled;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake();
});

// ── Happy path ────────────────────────────────────────────────────────────────

it('creates an admin_note intervention and audit_log row', function (): void {
    $booking = Booking::factory()->submitted()->create();
    $adminId = 1;
    $note = 'This is a test note for the booking.';

    $action = app(CreateAdminInterventionNoteAction::class);
    $intervention = $action->execute($booking, $adminId, $note);

    expect($intervention)->toBeInstanceOf(BookingAdminIntervention::class);
    expect($intervention->intervention_type)->toBe(InterventionType::AdminNote);
    expect($intervention->reason)->toBe($note);

    $this->assertDatabaseHas('booking_admin_interventions', [
        'booking_id'        => $booking->id,
        'admin_id'          => $adminId,
        'intervention_type' => 'admin_note',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Booking::class,
        'auditable_id'   => $booking->id,
        'action'         => 'booking.admin_note',
    ]);
})->group('booking', 'intervention');

// ── Validation: note length ───────────────────────────────────────────────────

it('throws InvalidArgumentException when note is empty', function (): void {
    $booking = Booking::factory()->submitted()->create();

    expect(fn () => app(CreateAdminInterventionNoteAction::class)->execute($booking, 1, ''))
        ->toThrow(\InvalidArgumentException::class);
})->group('booking', 'intervention');

it('throws InvalidArgumentException when note exceeds 2000 characters', function (): void {
    $booking = Booking::factory()->submitted()->create();
    $note = str_repeat('a', 2001);

    expect(fn () => app(CreateAdminInterventionNoteAction::class)->execute($booking, 1, $note))
        ->toThrow(\InvalidArgumentException::class);
})->group('booking', 'intervention');

it('accepts a note of exactly 2000 characters', function (): void {
    $booking = Booking::factory()->submitted()->create();
    $note = str_repeat('a', 2000);

    $intervention = app(CreateAdminInterventionNoteAction::class)->execute($booking, 1, $note);

    expect($intervention)->toBeInstanceOf(BookingAdminIntervention::class);
})->group('booking', 'intervention');

// ── No side effects ───────────────────────────────────────────────────────────

it('creates no notification_dispatches row', function (): void {
    $countBefore = \Illuminate\Support\Facades\DB::table('notification_dispatches')->count();
    $booking = Booking::factory()->submitted()->create();

    app(CreateAdminInterventionNoteAction::class)->execute($booking, 1, 'Checking side effects.');

    expect(\Illuminate\Support\Facades\DB::table('notification_dispatches')->count())->toBe($countBefore);
})->group('booking', 'intervention');

it('does not mutate booking state', function (): void {
    $booking = Booking::factory()->submitted()->create();
    $lifecycleBefore = $booking->lifecycle_status;

    app(CreateAdminInterventionNoteAction::class)->execute($booking, 1, 'No state mutation expected.');

    $booking->refresh();
    expect($booking->lifecycle_status)->toEqual($lifecycleBefore);
})->group('booking', 'intervention');

it('fires no domain events', function (): void {
    $booking = Booking::factory()->submitted()->create();
    Event::fake(); // re-fake after factory to track only events from the action forward

    app(CreateAdminInterventionNoteAction::class)->execute($booking, 1, 'Silent action test.');

    Event::assertNotDispatched(BookingCancelled::class);
    Event::assertNotDispatched(BookingChatFrozen::class);
    Event::assertNotDispatched(BookingChatResumed::class);
    Event::assertNotDispatched(BookingForceCancelled::class);
})->group('booking', 'intervention');
