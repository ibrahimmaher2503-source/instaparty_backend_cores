<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
});

// =========================================================================
// Helpers
// =========================================================================

function makeVendorWithType(ProductType $type): array
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    VendorApprovedProductTypeFactory::new()->forType($type)->create([
        'vendor_profile_id' => $vendor->id,
    ]);
    $category = Category::factory()->create([
        'allowed_product_types' => [$type->value],
    ]);

    return compact('user', 'vendor', 'category');
}

function makeCustomer(): User
{
    return User::factory()->phoneVerified()->asCustomer()->create();
}

function baseServicePayload(int $categoryId): array
{
    return [
        'name' => ['en' => 'Test Service EN', 'ar' => 'خدمة اختبار AR'],
        'short_description' => ['en' => 'Short desc EN', 'ar' => 'وصف قصير AR'],
        'category_id' => $categoryId,
        'base_price_minor' => 150000,
    ];
}

// =========================================================================
// RENTAL
// =========================================================================

it('vendor approved for rental creates a rental service (201 + public_id)', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Rental);

    $payload = array_merge(baseServicePayload($category->id), [
        'requires_electricity' => false,
        'requires_outdoor_space' => false,
        'default_rental_duration_hours' => 4,
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/vendor/services/rental', $payload);

    $response->assertStatus(201);
    $response->assertJsonPath('data.product_type', 'rental');
    $this->assertNotNull($response->json('data.public_id'));
    $this->assertDatabaseCount('services', 1);
    $this->assertDatabaseCount('service_rental_details', 1);
})->group('catalog', 'rental');

it('unauthenticated request to create rental service returns 401', function () {
    $category = Category::factory()->create();

    $payload = array_merge(baseServicePayload($category->id), [
        'default_rental_duration_hours' => 4,
    ]);

    $this->postJson('/api/v1/vendor/services/rental', $payload)
        ->assertStatus(401);
})->group('catalog', 'rental');

it('customer role cannot create a rental service (403)', function () {
    $customer = makeCustomer();
    $category = Category::factory()->create();

    $payload = array_merge(baseServicePayload($category->id), [
        'default_rental_duration_hours' => 4,
    ]);

    $this->actingAs($customer)
        ->postJson('/api/v1/vendor/services/rental', $payload)
        ->assertStatus(403);
})->group('catalog', 'rental');

it('vendor not approved for rental type is denied (403)', function () {
    // Vendor approved for sale, not rental
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Sale);

    $payload = array_merge(baseServicePayload($category->id), [
        'default_rental_duration_hours' => 4,
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/vendor/services/rental', $payload)
        ->assertStatus(403);
})->group('catalog', 'rental');

it('missing default_rental_duration_hours fails validation (422)', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Rental);

    $payload = baseServicePayload($category->id); // missing required rental-specific field

    $this->actingAs($user)
        ->postJson('/api/v1/vendor/services/rental', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['default_rental_duration_hours']);
})->group('catalog', 'rental');

it('rental service name is returned in EN locale', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Rental);

    $payload = array_merge(baseServicePayload($category->id), [
        'name' => ['en' => 'English Name', 'ar' => 'الاسم عربي'],
        'default_rental_duration_hours' => 4,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'en'])
        ->postJson('/api/v1/vendor/services/rental', $payload);

    $response->assertStatus(201);
    expect($response->json('data.name'))->toBe('English Name');
})->group('catalog', 'rental');

it('rental service name is returned in AR locale', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Rental);

    $payload = array_merge(baseServicePayload($category->id), [
        'name' => ['en' => 'English Name', 'ar' => 'الاسم عربي'],
        'default_rental_duration_hours' => 4,
    ]);

    app()->setLocale('ar');

    $response = $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'ar'])
        ->postJson('/api/v1/vendor/services/rental', $payload);

    $response->assertStatus(201);
    expect($response->json('data.name'))->toBe('الاسم عربي');

    app()->setLocale('en');
})->group('catalog', 'rental');

// =========================================================================
// SALE
// =========================================================================

it('vendor approved for sale creates a sale service (201 + public_id)', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Sale);

    $payload = array_merge(baseServicePayload($category->id), [
        'is_perishable' => true,
        'is_made_to_order' => false,
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/vendor/services/sale', $payload);

    $response->assertStatus(201);
    $response->assertJsonPath('data.product_type', 'sale');
    $this->assertNotNull($response->json('data.public_id'));
    $this->assertDatabaseCount('services', 1);
    $this->assertDatabaseCount('service_sale_details', 1);
})->group('catalog', 'sale');

it('unauthenticated request to create sale service returns 401', function () {
    $category = Category::factory()->create();

    $this->postJson('/api/v1/vendor/services/sale', baseServicePayload($category->id))
        ->assertStatus(401);
})->group('catalog', 'sale');

it('customer role cannot create a sale service (403)', function () {
    $customer = makeCustomer();
    $category = Category::factory()->create();

    $this->actingAs($customer)
        ->postJson('/api/v1/vendor/services/sale', baseServicePayload($category->id))
        ->assertStatus(403);
})->group('catalog', 'sale');

it('vendor not approved for sale type is denied (403)', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Rental);

    $this->actingAs($user)
        ->postJson('/api/v1/vendor/services/sale', baseServicePayload($category->id))
        ->assertStatus(403);
})->group('catalog', 'sale');

it('missing name.en fails validation for sale service (422)', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Sale);

    $payload = [
        'name' => ['ar' => 'اسم AR فقط'], // missing 'en'
        'short_description' => ['en' => 'Short desc EN', 'ar' => 'وصف قصير AR'],
        'category_id' => $category->id,
        'base_price_minor' => 80000,
    ];

    $this->actingAs($user)
        ->postJson('/api/v1/vendor/services/sale', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name.en']);
})->group('catalog', 'sale');

it('sale service name is returned in EN locale', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Sale);

    $payload = array_merge(baseServicePayload($category->id), [
        'name' => ['en' => 'Sale EN', 'ar' => 'بيع AR'],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'en'])
        ->postJson('/api/v1/vendor/services/sale', $payload);

    $response->assertStatus(201);
    expect($response->json('data.name'))->toBe('Sale EN');
})->group('catalog', 'sale');

it('sale service name is returned in AR locale', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Sale);

    $payload = array_merge(baseServicePayload($category->id), [
        'name' => ['en' => 'Sale EN', 'ar' => 'بيع AR'],
    ]);

    app()->setLocale('ar');

    $response = $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'ar'])
        ->postJson('/api/v1/vendor/services/sale', $payload);

    $response->assertStatus(201);
    expect($response->json('data.name'))->toBe('بيع AR');

    app()->setLocale('en');
})->group('catalog', 'sale');

// =========================================================================
// DIGITAL
// =========================================================================

it('vendor approved for digital creates a digital service (201 + public_id)', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Digital);

    $payload = array_merge(baseServicePayload($category->id), [
        'delivery_method' => 'email',
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/vendor/services/digital', $payload);

    $response->assertStatus(201);
    $response->assertJsonPath('data.product_type', 'digital');
    $this->assertNotNull($response->json('data.public_id'));
    $this->assertDatabaseCount('services', 1);
    $this->assertDatabaseCount('service_digital_details', 1);
})->group('catalog', 'digital');

it('unauthenticated request to create digital service returns 401', function () {
    $category = Category::factory()->create();

    $this->postJson('/api/v1/vendor/services/digital', baseServicePayload($category->id))
        ->assertStatus(401);
})->group('catalog', 'digital');

it('customer role cannot create a digital service (403)', function () {
    $customer = makeCustomer();
    $category = Category::factory()->create();

    $this->actingAs($customer)
        ->postJson('/api/v1/vendor/services/digital', baseServicePayload($category->id))
        ->assertStatus(403);
})->group('catalog', 'digital');

it('vendor not approved for digital type is denied (403)', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Rental);

    $this->actingAs($user)
        ->postJson('/api/v1/vendor/services/digital', baseServicePayload($category->id))
        ->assertStatus(403);
})->group('catalog', 'digital');

it('missing delivery_method fails validation for digital service (422)', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Digital);

    // delivery_method is required but omitted
    $payload = baseServicePayload($category->id);

    $this->actingAs($user)
        ->postJson('/api/v1/vendor/services/digital', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['delivery_method']);
})->group('catalog', 'digital');

it('digital service name is returned in EN locale', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Digital);

    $payload = array_merge(baseServicePayload($category->id), [
        'name' => ['en' => 'Digital EN', 'ar' => 'رقمي AR'],
        'delivery_method' => 'link',
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'en'])
        ->postJson('/api/v1/vendor/services/digital', $payload);

    $response->assertStatus(201);
    expect($response->json('data.name'))->toBe('Digital EN');
})->group('catalog', 'digital');

it('digital service name is returned in AR locale', function () {
    ['user' => $user, 'category' => $category] = makeVendorWithType(ProductType::Digital);

    $payload = array_merge(baseServicePayload($category->id), [
        'name' => ['en' => 'Digital EN', 'ar' => 'رقمي AR'],
        'delivery_method' => 'link',
    ]);

    app()->setLocale('ar');

    $response = $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'ar'])
        ->postJson('/api/v1/vendor/services/digital', $payload);

    $response->assertStatus(201);
    expect($response->json('data.name'))->toBe('رقمي AR');

    app()->setLocale('en');
})->group('catalog', 'digital');
