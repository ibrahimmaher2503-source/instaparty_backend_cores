<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\VendorModificationProposed;
use App\Modules\Booking\Domain\Models\BookingModification;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    Cache::flush();
});

function previewPayload(string $itemPublicId, int $price = 60000): array
{
    return [
        'proposal_kind' => 'change_price',
        'vendor_explanation' => ['en' => 'Updated pricing', 'ar' => 'تسعير محدث'],
        'changes' => [
            [
                'change_kind' => 'update',
                'target_item_public_id' => $itemPublicId,
                'payload' => ['unit_price_minor' => $price],
            ],
        ],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/v1/vendor/booking-vendors/{id}/preview-modification (G6)
// ─────────────────────────────────────────────────────────────────────────────

it('returns a diff and a preview token without writing to the database', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    Event::fake();

    $response = $this->actingAs($vendor->user)
        ->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/preview-modification", previewPayload($item->public_id))
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'diff' => ['before' => ['items', 'subtotal_minor'], 'after' => ['items', 'subtotal_minor']],
            'preview_token',
            'expires_at',
        ]]);

    expect($response->json('data.diff.after.subtotal_minor'))->toBe(60000)
        // Scoped to our booking-vendor: the global DatabaseSeeder ($seed=true)
        // ships demo modifications for other vendors.
        ->and(BookingModification::where('booking_vendor_id', $bv->id)->count())->toBe(0)
        ->and($bv->fresh()->sub_status->value)->toBe('pending');

    Event::assertNotDispatched(VendorModificationProposed::class);
})->group('booking', 'preview-modification');

it('issues a different preview token on each call', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();
    $url = "/api/v1/vendor/booking-vendors/{$bv->public_id}/preview-modification";

    $first = $this->actingAs($vendor->user)->postJson($url, previewPayload($item->public_id))->json('data.preview_token');
    $second = $this->actingAs($vendor->user)->postJson($url, previewPayload($item->public_id))->json('data.preview_token');

    expect($first)->not->toBe($second);
})->group('booking', 'preview-modification');

it('accepts a modification submitted with a valid preview token and consumes it', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();
    $payload = previewPayload($item->public_id);

    $token = $this->actingAs($vendor->user)
        ->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/preview-modification", $payload)
        ->json('data.preview_token');

    $this->actingAs($vendor->user)
        ->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/modify", [...$payload, 'preview_token' => $token])
        ->assertCreated();

    // Token consumed — replaying it is 410 (a pending modification also exists,
    // but the token check runs first).
    $this->actingAs($vendor->user)
        ->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/modify", [...$payload, 'preview_token' => $token])
        ->assertStatus(410);
})->group('booking', 'preview-modification');

it('returns 410 for an unknown or expired preview token', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $this->actingAs($vendor->user)
        ->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/modify", [
            ...previewPayload($item->public_id),
            'preview_token' => '01JUNKTOKEN0000000000000000',
        ])
        ->assertStatus(410);
})->group('booking', 'preview-modification');

it('returns 409 when submitted changes differ from the previewed ones', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $token = $this->actingAs($vendor->user)
        ->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/preview-modification", previewPayload($item->public_id, 60000))
        ->json('data.preview_token');

    $this->actingAs($vendor->user)
        ->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/modify", [
            ...previewPayload($item->public_id, 99000), // drifted price
            'preview_token' => $token,
        ])
        ->assertStatus(409);
})->group('booking', 'preview-modification');

it('still accepts a modification without a preview token (portal flow)', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $this->actingAs($vendor->user)
        ->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/modify", previewPayload($item->public_id))
        ->assertCreated();
})->group('booking', 'preview-modification');

it('returns 403 when another vendor previews', function (): void {
    ['bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();
    ['vendor' => $otherVendor] = makeSubmittedBookingWithVendor();

    $this->actingAs($otherVendor->user)
        ->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/preview-modification", previewPayload($item->public_id))
        ->assertStatus(403);
})->group('booking', 'preview-modification', 'auth');

it('returns 409 when previewing a non-pending booking vendor', function (): void {
    ['vendor' => $vendor, 'bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();
    $bv->update(['sub_status' => VendorSubStatus::Accepted]);

    $this->actingAs($vendor->user)
        ->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/preview-modification", previewPayload($item->public_id))
        ->assertStatus(409);
})->group('booking', 'preview-modification');

it('returns 401 when unauthenticated on preview', function (): void {
    ['bookingVendor' => $bv, 'item' => $item] = makeSubmittedBookingWithVendor();

    $this->postJson("/api/v1/vendor/booking-vendors/{$bv->public_id}/preview-modification", previewPayload($item->public_id))
        ->assertStatus(401);
})->group('booking', 'preview-modification', 'auth');
