<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\ResumeBookingReviewAction;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Events\CustomerReviewReminderSent;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake();

    // Bind a mock dispatcher so the action doesn't fail on dispatch
    $this->dispatcher = Mockery::mock(NotificationDispatcher::class);
    $this->dispatcher->shouldReceive('dispatch')->andReturnNull();
    $this->app->instance(NotificationDispatcher::class, $this->dispatcher);
});

// ── Happy path ────────────────────────────────────────────────────────────────

it('creates CustomerReviewReminder intervention + audit_log + fires event', function (): void {
    ['booking' => $booking] = makeBookingWithPendingModification();
    $adminId = 1;

    $action = app(ResumeBookingReviewAction::class);
    $intervention = $action->execute($booking, $adminId, 'Please review the vendor modification.');

    expect($intervention)->toBeInstanceOf(BookingAdminIntervention::class);
    expect($intervention->intervention_type)->toBe(InterventionType::CustomerReviewReminder);

    $this->assertDatabaseHas('booking_admin_interventions', [
        'booking_id'        => $booking->id,
        'admin_id'          => $adminId,
        'intervention_type' => 'customer_review_reminder',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Booking::class,
        'auditable_id'   => $booking->id,
        'action'         => 'booking.customer_review_reminder',
    ]);

    Event::assertDispatched(CustomerReviewReminderSent::class, function ($e) use ($booking, $adminId): bool {
        return $e->bookingId === $booking->id && $e->adminId === $adminId;
    });
})->group('booking', 'intervention');

it('does not mutate bookings, booking_vendors, or booking_modifications', function (): void {
    ['booking' => $booking, 'bookingVendor' => $bookingVendor, 'modification' => $modification] = makeBookingWithPendingModification();

    $lifecycleBefore = $booking->lifecycle_status;
    $subStatusBefore = $bookingVendor->sub_status;
    $modStatusBefore = $modification->status;

    app(ResumeBookingReviewAction::class)->execute($booking, 1);

    $booking->refresh();
    $bookingVendor->refresh();
    $modification->refresh();

    expect($booking->lifecycle_status)->toEqual($lifecycleBefore);
    expect($bookingVendor->sub_status)->toEqual($subStatusBefore);
    expect($modification->status)->toEqual($modStatusBefore);
})->group('booking', 'intervention');

// ── Guard: throws when no open modification ───────────────────────────────────

it('throws DomainException when no open modification exists', function (): void {
    $booking = Booking::factory()->submitted()->create();

    expect(fn () => app(ResumeBookingReviewAction::class)->execute($booking, 1))
        ->toThrow(\DomainException::class);
})->group('booking', 'intervention');

// ── Throttle: second call within cooldown throws ──────────────────────────────

it('throws DomainException on second call within throttle window', function (): void {
    ['booking' => $booking] = makeBookingWithPendingModification();
    $adminId = 1;

    // First call must succeed
    app(ResumeBookingReviewAction::class)->execute($booking, $adminId);

    // Second call within TTL must throw
    expect(fn () => app(ResumeBookingReviewAction::class)->execute($booking, $adminId))
        ->toThrow(\DomainException::class);
})->group('booking', 'intervention');
