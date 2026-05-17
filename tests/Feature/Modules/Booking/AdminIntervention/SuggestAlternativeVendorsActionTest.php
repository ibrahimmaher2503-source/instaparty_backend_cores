<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\SuggestAlternativeVendorsAction;
use App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO;
use App\Modules\Booking\Domain\Events\AdminSuggestedAlternativeVendors;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake();
});

it('creates vendor_proposal intervention with null proposed_vendor_id', function (): void {
    $finder = Mockery::mock(AlternativeVendorFinder::class);
    $finder->shouldReceive('validateCandidates')->once();
    $this->app->instance(AlternativeVendorFinder::class, $finder);

    $booking = Booking::factory()->submitted()->create();

    $dto = new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: 1,
        vendorProfileIds: [101, 102],
        reason: 'These vendors can cover the event',
    );

    $action = app(SuggestAlternativeVendorsAction::class);
    $intervention = $action->execute($booking, $dto);

    expect($intervention)->toBeInstanceOf(BookingAdminIntervention::class);
    expect($intervention->intervention_type->value)->toBe('vendor_proposal');
    expect($intervention->proposed_vendor_id)->toBeNull('proposed_vendor_id must always be null');

    $this->assertDatabaseHas('booking_admin_interventions', [
        'booking_id'         => $booking->id,
        'intervention_type'  => 'vendor_proposal',
        'proposed_vendor_id' => null,
    ]);

    // No booking_vendors row must be created by this action
    $this->assertDatabaseMissing('booking_vendors', [
        'booking_id' => $booking->id,
    ]);

    Event::assertDispatched(AdminSuggestedAlternativeVendors::class);
})->group('booking', 'intervention');

it('throws when vendor list is empty', function (): void {
    $booking = Booking::factory()->submitted()->create();

    $dto = new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: 1,
        vendorProfileIds: [],
        reason: 'No vendors',
    );

    expect(fn () => app(SuggestAlternativeVendorsAction::class)->execute($booking, $dto))
        ->toThrow(\InvalidArgumentException::class);
})->group('booking', 'intervention');

it('throws when vendor list exceeds config max', function (): void {
    $booking = Booking::factory()->submitted()->create();

    $dto = new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: 1,
        vendorProfileIds: [1, 2, 3, 4, 5, 6],
        reason: 'Too many',
    );

    expect(fn () => app(SuggestAlternativeVendorsAction::class)->execute($booking, $dto))
        ->toThrow(\InvalidArgumentException::class);
})->group('booking', 'intervention');

it('inserts an audit_log row on success', function (): void {
    $finder = Mockery::mock(AlternativeVendorFinder::class);
    $finder->shouldReceive('validateCandidates')->once();
    $this->app->instance(AlternativeVendorFinder::class, $finder);

    $booking = Booking::factory()->submitted()->create();

    $dto = new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: 1,
        vendorProfileIds: [101],
        reason: 'Testing audit trail',
    );

    app(SuggestAlternativeVendorsAction::class)->execute($booking, $dto);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Booking::class,
        'auditable_id'   => $booking->id,
        'action'         => 'booking.suggest_alternatives',
    ]);
})->group('booking', 'intervention');
