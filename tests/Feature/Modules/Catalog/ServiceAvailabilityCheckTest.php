<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\HoldServiceInventoryAction;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Identity\Domain\Models\User;

/**
 * Gap-closure Phase 3 P1 — POST /customer/services/{id}/check-availability.
 * Read-only probe; reservation happens at submit (HoldServiceInventoryAction).
 * Type-aware → all three product types covered (Tech Decisions §2.4).
 */
function availabilityUrl(Service $service): string
{
    return '/api/v1/customer/services/'.$service->public_id.'/check-availability';
}

it('reports a free rental window as available', function (): void {
    $service = Service::factory()->rental()->published()->create();
    ServiceRentalDetail::factory()->create(['service_id' => $service->id]);

    $this->postJson(availabilityUrl($service), [
        'starts_at' => now()->addDays(10)->toIso8601String(),
        'ends_at' => now()->addDays(10)->addHours(5)->toIso8601String(),
    ])->assertStatus(200)
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.product_type', 'rental')
        ->assertJsonPath('data.reason_code', null)
        ->assertJsonPath('data.reserved_at', 'submit');
})->group('catalog', 'availability', 'rental');

it('reports an overlapping rental window as unavailable', function (): void {
    $service = Service::factory()->rental()->published()->create();
    ServiceRentalDetail::factory()->create(['service_id' => $service->id]);
    $holder = User::factory()->create();

    app(HoldServiceInventoryAction::class)->execute(
        $service->refresh(),
        HoldType::Payment,
        $holder->id,
        now()->addDays(10),
        now()->addDays(10)->addHours(5),
    );

    $this->postJson(availabilityUrl($service), [
        'starts_at' => now()->addDays(10)->addHours(2)->toIso8601String(),
        'ends_at' => now()->addDays(10)->addHours(7)->toIso8601String(),
    ])->assertStatus(200)
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.reason_code', 'window_unavailable');
})->group('catalog', 'availability', 'rental');

it('requires a window for rentals', function (): void {
    $service = Service::factory()->rental()->published()->create();
    ServiceRentalDetail::factory()->create(['service_id' => $service->id]);

    $this->postJson(availabilityUrl($service), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['starts_at']);
})->group('catalog', 'availability', 'rental');

it('counts held stock against sale availability', function (): void {
    $service = Service::factory()->sale()->published()->create();
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 5]);
    $holder = User::factory()->create();

    app(HoldServiceInventoryAction::class)->execute(
        $service->refresh(),
        HoldType::Cart,
        $holder->id,
        quantity: 3,
    );

    $this->postJson(availabilityUrl($service), ['quantity' => 2])
        ->assertStatus(200)
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.remaining_quantity', 2);

    $this->postJson(availabilityUrl($service), ['quantity' => 3])
        ->assertStatus(200)
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.reason_code', 'insufficient_stock');
})->group('catalog', 'availability', 'sale');

it('treats made-to-order sale services as always available', function (): void {
    $service = Service::factory()->sale()->published()->create();
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => null]);

    $this->postJson(availabilityUrl($service), ['quantity' => 50])
        ->assertStatus(200)
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.remaining_quantity', null);
})->group('catalog', 'availability', 'sale');

it('treats digital services as always available', function (): void {
    $service = Service::factory()->digital()->published()->create();
    ServiceDigitalDetail::factory()->create(['service_id' => $service->id]);

    $this->postJson(availabilityUrl($service), [])
        ->assertStatus(200)
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.product_type', 'digital');
})->group('catalog', 'availability', 'digital');

it('404s for unpublished services', function (): void {
    $service = Service::factory()->rental()->create(); // draft
    ServiceRentalDetail::factory()->create(['service_id' => $service->id]);

    $this->postJson(availabilityUrl($service), [
        'starts_at' => now()->addDays(10)->toIso8601String(),
        'ends_at' => now()->addDays(10)->addHours(5)->toIso8601String(),
    ])->assertStatus(404);
})->group('catalog', 'availability');
