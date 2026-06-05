<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Str;

/**
 * Gap-closure Phase 3 P0 — contract tests pinning the EXACT envelopes the
 * Flutter diff viewer consumes. These shapes are documented in
 * docs/api/customer-modification-envelopes.md; a failure here means a breaking
 * client-contract change — do not "fix the test", fix the change.
 */
it('pins the modifications list envelope', function (): void {
    $data = makeBookingWithPendingModification();

    $response = $this->actingAs($data['customer'])
        ->getJson('/api/v1/customer/bookings/'.$data['booking']->public_id.'/modifications')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [['public_id', 'booking_vendor_id', 'proposed_by_role', 'proposal_kind', 'status', 'vendor_explanation', 'diff_snapshot', 'created_at']],
            'meta',
            'errors',
        ]);

    $modification = collect($response->json('data'))
        ->firstWhere('public_id', $data['modification']->public_id);

    expect(array_keys($modification))->toBe([
        'public_id', 'booking_vendor_id', 'proposed_by_role', 'proposal_kind',
        'status', 'vendor_explanation', 'diff_snapshot', 'created_at',
    ])->and($modification['proposed_by_role'])->toBe('vendor')
        ->and($modification['status'])->toBe('pending')
        ->and($modification['booking_vendor_id'])->toBe($data['bookingVendor']->public_id);
})->group('booking', 'envelope-contract');

it('pins the modification detail envelope and the before/after diff variant', function (): void {
    $data = makeBookingWithPendingModification();

    $response = $this->actingAs($data['customer'])
        ->getJson('/api/v1/customer/bookings/'.$data['booking']->public_id.'/modifications/'.$data['modification']->public_id)
        ->assertStatus(200);

    // Variant B (vendor modify/preview path): before/after item snapshots.
    expect($response->json('data.diff_snapshot'))->toHaveKeys(['before', 'after'])
        ->and($response->json('data.diff_snapshot.before'))->toHaveKeys(['items', 'subtotal_minor'])
        ->and($response->json('data.diff_snapshot.after.subtotal_minor'))->toBe(60000);
})->group('booking', 'envelope-contract');

it('pins the decide(accept) envelope: full refreshed booking with server totals', function (): void {
    $data = makeBookingWithPendingModification();

    $response = $this->actingAs($data['customer'])
        ->postJson(
            '/api/v1/customer/bookings/'.$data['booking']->public_id.'/modifications/'.$data['modification']->public_id.'/decide',
            ['decision' => 'accepted'],
            ['Idempotency-Key' => (string) Str::uuid()],
        )
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'public_id', 'reference_no', 'lifecycle_status', 'display_status',
                'rejection_reason', 'vendors_summary', 'payment_status',
                'fulfillment_status', 'subtotal_minor', 'total_minor', 'due_minor',
                'currency', 'requires_customer_approval',
            ],
            'meta',
            'errors',
        ]);

    // Single-vendor booking: accepting the only pending proposal confirms it.
    // CONTRACT NUANCE: decide recalculates total_minor (and the per-vendor
    // subtotals) but NOT the booking-level subtotal_minor — clients must
    // display total_minor after a decision.
    expect($response->json('data.lifecycle_status'))->toBe('confirmed')
        ->and($response->json('data.total_minor'))->toBe(60000)
        ->and($response->json('data.requires_customer_approval'))->toBeFalse();
})->group('booking', 'envelope-contract');

it('pins the decide(reject) envelope and resulting modification status', function (): void {
    $data = makeBookingWithPendingModification();

    $this->actingAs($data['customer'])
        ->postJson(
            '/api/v1/customer/bookings/'.$data['booking']->public_id.'/modifications/'.$data['modification']->public_id.'/decide',
            ['decision' => 'rejected'],
            ['Idempotency-Key' => (string) Str::uuid()],
        )
        ->assertStatus(200)
        ->assertJsonStructure(['data' => ['public_id', 'lifecycle_status'], 'meta', 'errors']);

    expect($data['modification']->refresh()->status)->toBe(ModificationStatus::CustomerRejected);
})->group('booking', 'envelope-contract');

it('rejects an unknown decision value', function (): void {
    $data = makeBookingWithPendingModification();

    $this->actingAs($data['customer'])
        ->postJson(
            '/api/v1/customer/bookings/'.$data['booking']->public_id.'/modifications/'.$data['modification']->public_id.'/decide',
            ['decision' => 'countered'],
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['decision']);
})->group('booking', 'envelope-contract');

it('marks admin-proposed modifications with proposed_by_role=admin', function (): void {
    $data = makeBookingWithPendingModification();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $data['modification']->update(['proposed_by' => $admin->id]);

    $response = $this->actingAs($data['customer'])
        ->getJson('/api/v1/customer/bookings/'.$data['booking']->public_id.'/modifications/'.$data['modification']->public_id)
        ->assertStatus(200);

    expect($response->json('data.proposed_by_role'))->toBe('admin');
})->group('booking', 'envelope-contract');
