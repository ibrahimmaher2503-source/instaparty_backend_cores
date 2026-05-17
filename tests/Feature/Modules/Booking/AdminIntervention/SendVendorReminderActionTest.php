<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\SendVendorReminderAction;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\VendorReminderSent;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake();
    $this->dispatcher = $this->mock(NotificationDispatcher::class);
});

it('creates intervention, dispatches notification, and fires event after commit on happy path', function (): void {
    // Arrange
    $bookingVendor = BookingVendorFactory::new()->pending()->create();
    $adminId = 1;

    $this->dispatcher->shouldReceive('dispatch')->once();

    // Act
    $action = app(SendVendorReminderAction::class);
    $intervention = $action->execute($bookingVendor, $adminId, 'Please respond ASAP');

    // Assert
    expect($intervention)->toBeInstanceOf(BookingAdminIntervention::class);
    expect($intervention->intervention_type->value)->toBe('vendor_reminder');

    $this->assertDatabaseHas('booking_admin_interventions', [
        'booking_id' => $bookingVendor->booking_id,
        'admin_id' => $adminId,
        'intervention_type' => 'vendor_reminder',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => \App\Modules\Booking\Domain\Models\Booking::class,
        'action' => 'booking.vendor_reminder',
    ]);

    Event::assertDispatched(VendorReminderSent::class);
})->group('booking', 'intervention');

it('throws DomainException when vendor is not pending', function (): void {
    $bookingVendor = BookingVendorFactory::new()->create(['sub_status' => VendorSubStatus::Accepted]);

    expect(fn () => app(SendVendorReminderAction::class)->execute($bookingVendor, 1))
        ->toThrow(\DomainException::class);
})->group('booking', 'intervention');

it('throws DomainException when reminder is within throttle window', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create();

    $this->dispatcher->shouldReceive('dispatch');

    $action = app(SendVendorReminderAction::class);

    $action->execute($bookingVendor, 1, 'First reminder');

    // Second call within 5 min — should be throttled
    expect(fn () => $action->execute($bookingVendor, 1, 'Second reminder'))
        ->toThrow(\DomainException::class);
})->group('booking', 'intervention');
