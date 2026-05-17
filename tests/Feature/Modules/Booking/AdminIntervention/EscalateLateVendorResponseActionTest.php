<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\EscalateLateVendorResponseAction;
use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingVendorTimedOut;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Communication\Domain\Contracts\AdminInboxWriter;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake();
    $this->mock(NotificationDispatcher::class);

    $fakeItem = new AdminInboxItem();
    $this->mock(AdminInboxWriter::class)
        ->shouldReceive('create')
        ->andReturn($fakeItem);
});

it('flips sub_status to timed_out, writes state transition and intervention, and fires event on happy path', function (): void {
    // Arrange: vendor with deadline in the past
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => now()->subMinutes(30),
    ]);

    $dto = new AdminInterventionDTO(
        bookingId: $bookingVendor->booking_id,
        adminId: 1,
        interventionType: InterventionType::VendorTimeout,
        reason: 'Vendor did not respond within SLA.',
        bookingVendorId: $bookingVendor->id,
    );

    // Act
    $intervention = app(EscalateLateVendorResponseAction::class)->execute($bookingVendor, $dto);

    // Assert model returned
    expect($intervention)->toBeInstanceOf(BookingAdminIntervention::class);
    expect($intervention->intervention_type->value)->toBe('vendor_timeout');

    // Assert sub_status flipped
    $bookingVendor->refresh();
    expect($bookingVendor->sub_status)->toBe(VendorSubStatus::TimedOut);

    // Assert intervention row written
    $this->assertDatabaseHas('booking_admin_interventions', [
        'booking_id' => $bookingVendor->booking_id,
        'admin_id' => 1,
        'intervention_type' => 'vendor_timeout',
    ]);

    // Assert state transition written
    $this->assertDatabaseHas('state_transitions', [
        'transitionable_type' => \App\Modules\Booking\Domain\Models\BookingVendor::class,
        'transitionable_id' => $bookingVendor->id,
        'from_state' => 'pending',
        'to_state' => 'timed_out',
        'trigger_kind' => 'admin',
    ]);

    // Assert audit log
    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => \App\Modules\Booking\Domain\Models\Booking::class,
        'action' => 'booking.vendor_timeout',
    ]);

    // Assert event fired
    Event::assertDispatched(BookingVendorTimedOut::class, fn (BookingVendorTimedOut $e): bool =>
        $e->bookingVendorId === $bookingVendor->id &&
        $e->bookingId === $bookingVendor->booking_id &&
        $e->triggeredByAdminId === 1
    );
})->group('booking', 'intervention');

it('throws DomainException when response deadline has not yet passed', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => now()->addHours(2),
    ]);

    $dto = new AdminInterventionDTO(
        bookingId: $bookingVendor->booking_id,
        adminId: 1,
        interventionType: InterventionType::VendorTimeout,
        reason: 'Trying to escalate early.',
        bookingVendorId: $bookingVendor->id,
    );

    expect(fn () => app(EscalateLateVendorResponseAction::class)->execute($bookingVendor, $dto))
        ->toThrow(\DomainException::class);
})->group('booking', 'intervention');

it('throws DomainException when vendor sub_status is not pending', function (): void {
    $bookingVendor = BookingVendorFactory::new()->create([
        'sub_status' => VendorSubStatus::Accepted,
        'response_deadline' => now()->subMinutes(30),
    ]);

    $dto = new AdminInterventionDTO(
        bookingId: $bookingVendor->booking_id,
        adminId: 1,
        interventionType: InterventionType::VendorTimeout,
        reason: 'Already accepted vendor.',
        bookingVendorId: $bookingVendor->id,
    );

    expect(fn () => app(EscalateLateVendorResponseAction::class)->execute($bookingVendor, $dto))
        ->toThrow(\DomainException::class);
})->group('booking', 'intervention');
