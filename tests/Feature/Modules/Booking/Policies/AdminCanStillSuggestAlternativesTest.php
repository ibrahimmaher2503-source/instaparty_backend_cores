<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\SuggestAlternativeVendorsAction;
use App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;

it('admin with suggest_alternative_vendors permission can execute the suggestion action', function (): void {
    Event::fake();

    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo('booking.intervene.suggest_alternative_vendors');

    $finder = Mockery::mock(AlternativeVendorFinder::class);
    $finder->shouldReceive('validateCandidates')->once();
    app()->instance(AlternativeVendorFinder::class, $finder);

    $booking = Booking::factory()->submitted()->create();
    $dto = new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: $admin->id,
        vendorProfileIds: [101, 102],
        reason: 'US2 regression smoke test',
    );

    $intervention = app(SuggestAlternativeVendorsAction::class)->execute($booking, $dto);

    expect($intervention)->toBeInstanceOf(BookingAdminIntervention::class);
    expect($intervention->intervention_type->value)->toBe('vendor_proposal');
    expect($intervention->proposed_vendor_id)->toBeNull('proposed_vendor_id must always be null');
    expect($intervention->after_state['suggested_vendor_ids'])->toBe([101, 102]);
})->group('booking', 'policy', 'replacement-vendor-guard');

it('suggestion path writes booking.suggest_alternatives audit row', function (): void {
    Event::fake();

    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo('booking.intervene.suggest_alternative_vendors');

    $finder = Mockery::mock(AlternativeVendorFinder::class);
    $finder->shouldReceive('validateCandidates')->once();
    app()->instance(AlternativeVendorFinder::class, $finder);

    $booking = Booking::factory()->submitted()->create();
    $dto = new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: $admin->id,
        vendorProfileIds: [101],
        reason: 'audit row check',
    );

    app(SuggestAlternativeVendorsAction::class)->execute($booking, $dto);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Booking::class,
        'auditable_id'   => $booking->id,
        'action'         => 'booking.suggest_alternatives',
    ]);
})->group('booking', 'policy', 'replacement-vendor-guard');

it('suggestion path does NOT trip the replacement_vendor_assignment_blocked tripwire', function (): void {
    Event::fake();

    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo('booking.intervene.suggest_alternative_vendors');

    $finder = Mockery::mock(AlternativeVendorFinder::class);
    $finder->shouldReceive('validateCandidates')->once();
    app()->instance(AlternativeVendorFinder::class, $finder);

    $booking = Booking::factory()->submitted()->create();
    $dto = new SuggestedAlternativeVendorsDTO(
        bookingId: $booking->id,
        adminId: $admin->id,
        vendorProfileIds: [101],
        reason: 'non-interference check',
    );

    app(SuggestAlternativeVendorsAction::class)->execute($booking, $dto);

    expect(
        DB::table('audit_logs')
            ->where('action', 'booking.replacement_vendor_assignment_blocked')
            ->where('auditable_id', $booking->id)
            ->count()
    )->toBe(0, 'Suggestion path must never fire the replacement-blocked tripwire');
})->group('booking', 'policy', 'replacement-vendor-guard');
