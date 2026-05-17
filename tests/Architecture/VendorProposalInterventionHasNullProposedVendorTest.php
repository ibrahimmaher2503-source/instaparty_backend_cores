<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Policies\BookingAdminInterventionPolicy;
use App\Modules\Booking\Domain\Enums\InterventionType;

it('enforces FR-EXT-012: BookingAdminInterventionPolicy rejects vendor_proposal with non-null proposed_vendor_id', function (): void {
    $policy = new BookingAdminInterventionPolicy();

    $user = Mockery::mock(\App\Modules\Identity\Domain\Models\User::class);
    $user->shouldReceive('can')->with('booking.intervene.suggest_alternative_vendors')->andReturn(true);

    $intervention = new \App\Modules\Booking\Domain\Models\BookingAdminIntervention();
    $intervention->intervention_type = InterventionType::VendorProposal;
    $intervention->proposed_vendor_id = 999; // non-null — must be rejected

    expect($policy->create($user, $intervention))->toBeFalse(
        'Policy must return false when vendor_proposal intervention has non-null proposed_vendor_id'
    );
})->group('architecture');

it('enforces FR-EXT-012: vendor_proposal with null proposed_vendor_id is allowed', function (): void {
    $policy = new BookingAdminInterventionPolicy();

    $user = Mockery::mock(\App\Modules\Identity\Domain\Models\User::class);
    $user->shouldReceive('can')->with('booking.intervene.suggest_alternative_vendors')->andReturn(true);

    $intervention = new \App\Modules\Booking\Domain\Models\BookingAdminIntervention();
    $intervention->intervention_type = InterventionType::VendorProposal;
    $intervention->proposed_vendor_id = null; // null — allowed

    expect($policy->create($user, $intervention))->toBeTrue(
        'Policy must return true when vendor_proposal intervention has null proposed_vendor_id'
    );
})->group('architecture');
