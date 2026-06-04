<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 3 D1/D2/D3 — modification detail (12.6), tax-invoice request
 * (12.10 foundational), review edit (13.3).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ---------------------------------------------------------------------------
// 12.6 — modification detail
// ---------------------------------------------------------------------------

it('shows a single modification with its diff snapshot', function (): void {
    $data = makeBookingWithPendingModification();

    $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}/modifications/{$data['modification']->public_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.public_id', $data['modification']->public_id)
        ->assertJsonStructure(['data' => ['public_id', 'proposal_kind', 'status', 'vendor_explanation', 'diff_snapshot', 'created_at']]);
})->group('booking', 'negotiation');

it('modification detail is 404 for another customer', function (): void {
    $data = makeBookingWithPendingModification();
    $other = User::factory()->asCustomer()->create();

    $this->actingAs($other)
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}/modifications/{$data['modification']->public_id}")
        ->assertStatus(404);
})->group('booking', 'negotiation', 'privacy');

// ---------------------------------------------------------------------------
// 12.10 — tax invoice (foundational)
// ---------------------------------------------------------------------------

it('stores the tax invoice request fields and audit-logs it', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $this->actingAs($data['customer'])
        ->postJson(
            "/api/v1/customer/bookings/{$data['booking']->public_id}/request-tax-invoice",
            ['invoice_name' => 'ACME LLC', 'invoice_tax_id' => 'EG-123-456'],
            ['Idempotency-Key' => (string) Str::uuid()],
        )
        ->assertStatus(200)
        ->assertJsonPath('data.requires_tax_invoice', true);

    $booking = $data['booking']->refresh();
    expect((bool) $booking->requires_tax_invoice)->toBeTrue()
        ->and($booking->invoice_name)->toBe('ACME LLC')
        ->and($booking->invoice_tax_id)->toBe('EG-123-456');

    expect(DB::table('audit_logs')
        ->where('auditable_id', $booking->id)
        ->where('action', 'customer_requested_tax_invoice')
        ->exists())->toBeTrue();
})->group('booking', 'tax-invoice');

it('tax invoice request validates required fields', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $this->actingAs($data['customer'])
        ->postJson(
            "/api/v1/customer/bookings/{$data['booking']->public_id}/request-tax-invoice",
            [],
            ['Idempotency-Key' => (string) Str::uuid()],
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['invoice_name', 'invoice_tax_id']);
})->group('booking', 'tax-invoice');

// ---------------------------------------------------------------------------
// 13.3 — review edit (pre-moderation only)
// ---------------------------------------------------------------------------

function polishPendingReview(): array
{
    $data = makeSubmittedBookingWithVendor();

    $review = ServiceReview::create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $data['service']->id,
        'booking_item_id' => $data['item']->id,
        'user_id' => $data['customer']->id,
        'rating' => 3,
        'body' => 'Initial text',
        'locale' => 'en',
        'moderation_status' => 'pending',
    ]);

    return [...$data, 'review' => $review];
}

it('customer edits an own pending review', function (): void {
    $data = polishPendingReview();

    $this->actingAs($data['customer'])
        ->patchJson("/api/v1/customer/reviews/service/{$data['review']->public_id}", [
            'rating' => 5,
            'body' => 'Updated — actually great!',
        ])
        ->assertStatus(200);

    $review = $data['review']->refresh();
    expect((int) $review->rating)->toBe(5)
        ->and($review->body)->toBe('Updated — actually great!')
        ->and($review->moderation_status->value)->toBe('pending');
})->group('reviews');

it('cannot edit a review after moderation approved it', function (): void {
    $data = polishPendingReview();
    $data['review']->update(['moderation_status' => 'approved']);

    $this->actingAs($data['customer'])
        ->patchJson("/api/v1/customer/reviews/service/{$data['review']->public_id}", ['rating' => 1])
        ->assertStatus(422);
})->group('reviews');

it('cannot edit another customer review — 404', function (): void {
    $data = polishPendingReview();
    $other = User::factory()->asCustomer()->create();

    $this->actingAs($other)
        ->patchJson("/api/v1/customer/reviews/service/{$data['review']->public_id}", ['rating' => 1])
        ->assertStatus(404);
})->group('reviews', 'privacy');
