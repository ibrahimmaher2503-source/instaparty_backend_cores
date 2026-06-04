<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 3 C1 — audit F8: customer vendor-browsing endpoints.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    Cache::flush();
    $this->vendor = VendorProfile::factory()->approved()->create();
});

it('lists the vendor published services filterable by product type', function (string $type): void {
    foreach (['rental', 'sale', 'digital'] as $t) {
        Service::factory()->{$t}()->published()->create(['vendor_profile_id' => $this->vendor->id]);
    }
    Service::factory()->rental()->create(['vendor_profile_id' => $this->vendor->id]); // draft — hidden

    $response = $this->getJson("/api/v1/customer/vendors/{$this->vendor->public_id}/services?type={$type}")
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.product_type'))->toBe($type);
})->with(['rental', 'sale', 'digital'])->group('discovery', 'vendor-browsing', 'rental', 'sale', 'digital');

it('returns coverage cities with localized names and delivery fees', function (): void {
    $city = City::factory()->create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
    VendorCoverageArea::create([
        'vendor_profile_id' => $this->vendor->id,
        'city_id' => $city->id,
        'delivery_fee_minor' => 5000,
        'delivery_fee_currency' => 'EGP',
        'min_order_minor' => 20000,
        'min_order_currency' => 'EGP',
    ]);

    $this->getJson("/api/v1/customer/vendors/{$this->vendor->public_id}/coverage")
        ->assertStatus(200)
        ->assertJsonPath('data.cities_count', 1)
        ->assertJsonPath('data.cities.0.delivery_fee_minor', 5000)
        ->assertJsonPath('data.cities.0.name', 'Cairo');
})->group('discovery', 'vendor-browsing');

it('exposes weekly hours and open-now state', function (): void {
    foreach (range(0, 6) as $day) {
        VendorBusinessHour::create([
            'vendor_profile_id' => $this->vendor->id,
            'day_of_week' => $day,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
        ]);
    }

    $response = $this->getJson("/api/v1/customer/vendors/{$this->vendor->public_id}/availability")
        ->assertStatus(200)
        ->assertJsonPath('data.is_open_now', true);

    expect($response->json('data.weekly_hours'))->toHaveCount(7)
        ->and($response->json('data.blocked_dates'))->toBe([]);
})->group('discovery', 'vendor-browsing');

it('availability check accepts a future date and reflects weekly hours', function (): void {
    $target = now('Africa/Cairo')->addDays(3);
    VendorBusinessHour::create([
        'vendor_profile_id' => $this->vendor->id,
        'day_of_week' => $target->dayOfWeek,
        'opens_at' => '10:00:00',
        'closes_at' => '22:00:00',
    ]);

    $this->postJson("/api/v1/customer/vendors/{$this->vendor->public_id}/availability/check", [
        'date' => $target->toDateString(),
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.is_available', true)
        ->assertJsonPath('data.opens_at', '10:00');

    $this->postJson("/api/v1/customer/vendors/{$this->vendor->public_id}/availability/check", [
        'date' => now()->subDay()->toDateString(),
    ])->assertStatus(422);
})->group('discovery', 'vendor-browsing');

it('portfolio aggregates gallery counts across published services', function (): void {
    Service::factory()->rental()->published()->create(['vendor_profile_id' => $this->vendor->id]);

    $this->getJson("/api/v1/customer/vendors/{$this->vendor->public_id}/portfolio")
        ->assertStatus(200)
        ->assertJsonPath('data.total_count', 0)
        ->assertJsonPath('data.items', []);
})->group('discovery', 'vendor-browsing');

it('composite profile carries stats, today_hours, coverage_summary, portfolio_preview', function (): void {
    Service::factory()->sale()->published()->create(['vendor_profile_id' => $this->vendor->id]);

    $response = $this->getJson("/api/v1/customer/vendors/{$this->vendor->public_id}")
        ->assertStatus(200);

    $data = $response->json('data');

    expect($data)->toHaveKeys(['stats', 'coverage_summary', 'today_hours', 'featured_review', 'top_services', 'portfolio_preview'])
        ->and($data['stats']['services_count'])->toBe(1)
        ->and($data['stats'])->not->toHaveKey('commission_rate')
        ->and($data)->not->toHaveKey('bank_iban');
})->group('discovery', 'vendor-browsing', 'privacy');

it('sub-resources 404 for a non-approved vendor', function (): void {
    $pending = VendorProfile::factory()->create();

    foreach (['services', 'coverage', 'availability', 'portfolio'] as $path) {
        $this->getJson("/api/v1/customer/vendors/{$pending->public_id}/{$path}")->assertStatus(404);
    }
})->group('discovery', 'vendor-browsing', 'privacy');
