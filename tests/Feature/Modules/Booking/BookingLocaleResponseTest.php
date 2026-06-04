<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Occasion;
use Database\Seeders\IdentityRolesSeeder;

/**
 * P1 — Customer API audit 2026-06-04, Step 1.5: bilingual response
 * correctness on a locale-middleware route (booking detail). Vendor
 * business_name is a spatie/laravel-translatable JSON column; the API
 * Resource resolves it per request locale (CLAUDE.md §9 — locale conversion
 * at the Resource layer).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function localeBookingFixture(): array
{
    $data = makeSubmittedBookingWithVendor();

    $data['vendor']->setTranslations('business_name', [
        'en' => 'Party Masters',
        'ar' => 'سادة الحفلات',
    ])->save();

    return $data;
}

it('booking detail resolves vendor name in English for Accept-Language en', function (): void {
    $data = localeBookingFixture();

    $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}", ['Accept-Language' => 'en'])
        ->assertStatus(200)
        ->assertJsonPath('data.vendors.0.vendor_name', 'Party Masters');
})->group('booking', 'locale');

it('booking detail resolves vendor name in Arabic for Accept-Language ar', function (): void {
    $data = localeBookingFixture();

    $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}", ['Accept-Language' => 'ar'])
        ->assertStatus(200)
        ->assertJsonPath('data.vendors.0.vendor_name', 'سادة الحفلات');
})->group('booking', 'locale');

it('explicit ?lang=ar overrides the Accept-Language header', function (): void {
    $data = localeBookingFixture();

    $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}?lang=ar", ['Accept-Language' => 'en'])
        ->assertStatus(200)
        ->assertJsonPath('data.vendors.0.vendor_name', 'سادة الحفلات');
})->group('booking', 'locale');

it('returns Arabic validation messages for Accept-Language ar')
    ->todo()
    // Audit 2026-06-04 gap (MEDIUM): no lang/{en,ar}/validation.php exists —
    // framework default English validation strings are returned regardless
    // of Accept-Language. Needs published + translated validation lang files.
    ->group('booking', 'locale');

it('public catalog routes honor Accept-Language', function (): void {
    // Closed in Phase 3 E: locale middleware added to the public
    // Catalog/Discovery/Geography route groups.
    $occasion = Occasion::factory()->create([
        'is_active' => true,
        'name' => ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
    ]);

    $this->getJson("/api/v1/customer/occasions/{$occasion->code}", ['Accept-Language' => 'ar'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'عيد ميلاد');

    $this->getJson("/api/v1/customer/occasions/{$occasion->code}", ['Accept-Language' => 'en'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Birthday');
})->group('catalog', 'locale');
