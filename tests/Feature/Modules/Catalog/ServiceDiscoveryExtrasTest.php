<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 D4 — suggestions (6.2), similar (7.4), view tracking (7.5),
 * current-draft (10.1), discard-draft (10.8).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('suggests published services matching the query in either locale', function (): void {
    $hit = Service::factory()->rental()->published()->create([
        'name' => ['en' => 'Unicorn Bouncy Castle', 'ar' => 'نطاطة يونيكورن'],
    ]);
    Service::factory()->rental()->create([ // draft — hidden
        'name' => ['en' => 'Unicorn Slide', 'ar' => 'زحليقة'],
    ]);

    $response = $this->getJson('/api/v1/customer/services/suggestions?q=Unicorn')
        ->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('public_id');
    expect($ids)->toContain($hit->public_id)->and($ids)->toHaveCount(1);

    $this->getJson('/api/v1/customer/services/suggestions?q=a')->assertStatus(422);
})->group('catalog', 'discovery');

it('lists similar services of the same category and type', function (): void {
    $anchor = Service::factory()->sale()->published()->create();
    $similar = Service::factory()->sale()->published()->create(['category_id' => $anchor->category_id]);
    $otherType = Service::factory()->digital()->published()->create(['category_id' => $anchor->category_id]);

    $response = $this->getJson("/api/v1/customer/services/{$anchor->public_id}/similar")
        ->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('public_id');
    expect($ids)->toContain($similar->public_id)
        ->and($ids)->not->toContain($anchor->public_id)
        ->and($ids)->not->toContain($otherType->public_id);
})->group('catalog', 'sale');

it('tracks a service view into analytics_events', function (): void {
    $service = Service::factory()->digital()->published()->create();

    $this->postJson("/api/v1/customer/services/{$service->public_id}/views")
        ->assertStatus(202);

    expect(DB::table('analytics_events')
        ->where('event_type', 'service_view')
        ->whereJsonContains('payload->service_id', $service->id)
        ->exists())->toBeTrue();
})->group('catalog', 'digital');

it('returns the latest open draft as current-draft and discards it', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $data['booking']->update(['lifecycle_status' => DraftState::class]);

    $this->actingAs($data['customer'])
        ->getJson('/api/v1/customer/bookings/current-draft')
        ->assertStatus(200)
        ->assertJsonPath('data.public_id', $data['booking']->public_id);

    $this->actingAs($data['customer'])
        ->deleteJson("/api/v1/customer/bookings/{$data['booking']->public_id}")
        ->assertStatus(200);

    expect($data['booking']->refresh()->trashed())->toBeTrue();

    $this->actingAs($data['customer'])
        ->getJson('/api/v1/customer/bookings/current-draft')
        ->assertStatus(404);
})->group('booking', 'draft');

it('cannot discard a non-draft booking — 422', function (): void {
    $data = makeSubmittedBookingWithVendor(); // vendor_review

    $this->actingAs($data['customer'])
        ->deleteJson("/api/v1/customer/bookings/{$data['booking']->public_id}")
        ->assertStatus(422);
})->group('booking', 'draft');

it('cannot discard another customer draft — 404', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $other = User::factory()->asCustomer()->create();

    $this->actingAs($other)
        ->deleteJson("/api/v1/customer/bookings/{$data['booking']->public_id}")
        ->assertStatus(404);
})->group('booking', 'draft', 'privacy');
