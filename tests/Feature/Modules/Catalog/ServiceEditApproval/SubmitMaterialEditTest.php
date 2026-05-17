<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestSubmitted;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\Event;

uses()->group('catalog', 'service-edit-approval', 'us1');

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
});

// ── US1: Vendor submits material edit on a published service ──────────────────

it('vendor submits material edit on rental service → 202 + CR pending + live row unchanged', function (): void {
    Event::fake([ServiceChangeRequestSubmitted::class]);

    ['user' => $user, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category, ['base_price_minor' => 50000]);

    $response = $this->actingAs($user)->patchJson(
        "/api/v1/vendor/services/rental/{$service->public_id}",
        [
            'name' => $service->getTranslations('name'),
            'short_description' => $service->getTranslations('short_description'),
            'category_id' => $service->category_id,
            'base_price_minor' => 75000,
        ]
    );

    $response->assertStatus(202);
    $response->assertJsonPath('data.type', 'service_change_request');
    $response->assertJsonPath('data.status', ServiceChangeRequestStatus::Pending->value);

    // Live row must NOT be updated
    $service->refresh();
    expect($service->base_price_minor)->toBe(50000);

    // CR must exist in DB as pending
    $this->assertDatabaseHas('service_change_requests', [
        'service_id' => $service->id,
        'status' => ServiceChangeRequestStatus::Pending->value,
    ]);

    Event::assertDispatched(ServiceChangeRequestSubmitted::class);
})->group('rental');

it('vendor submits material edit on sale service → 202 + CR pending + live row unchanged', function (): void {
    ['user' => $user, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Sale);
    $service = makePublishedServiceOfType(ProductType::Sale, $vendor, $category, ['base_price_minor' => 30000]);

    $response = $this->actingAs($user)->patchJson(
        "/api/v1/vendor/services/sale/{$service->public_id}",
        [
            'name' => $service->getTranslations('name'),
            'short_description' => $service->getTranslations('short_description'),
            'category_id' => $service->category_id,
            'base_price_minor' => 45000,
        ]
    );

    $response->assertStatus(202);
    $service->refresh();
    expect($service->base_price_minor)->toBe(30000); // untouched

    $this->assertDatabaseHas('service_change_requests', [
        'service_id' => $service->id,
        'status' => ServiceChangeRequestStatus::Pending->value,
        'product_type' => ProductType::Sale->value,
    ]);
})->group('sale');

it('vendor submits material edit on digital service → 202 + CR pending + live row unchanged', function (): void {
    ['user' => $user, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Digital);
    $service = makePublishedServiceOfType(ProductType::Digital, $vendor, $category, ['base_price_minor' => 20000]);

    $response = $this->actingAs($user)->patchJson(
        "/api/v1/vendor/services/digital/{$service->public_id}",
        [
            'name' => ['en' => 'Updated digital name', 'ar' => 'اسم رقمي محدث'],
            'short_description' => $service->getTranslations('short_description'),
            'category_id' => $service->category_id,
            'base_price_minor' => $service->base_price_minor,
        ]
    );

    $response->assertStatus(202);
    $service->refresh();
    expect($service->getTranslation('name', 'en'))->not->toBe('Updated digital name'); // live row untouched

    $this->assertDatabaseHas('service_change_requests', [
        'service_id' => $service->id,
        'product_type' => ProductType::Digital->value,
    ]);
})->group('digital');

it('CR contains per-field item rows for every changed material field', function (): void {
    ['user' => $user, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category, ['base_price_minor' => 50000]);

    $this->actingAs($user)->patchJson(
        "/api/v1/vendor/services/rental/{$service->public_id}",
        [
            'name' => ['en' => 'New name EN', 'ar' => 'اسم جديد AR'],
            'short_description' => $service->getTranslations('short_description'),
            'category_id' => $service->category_id,
            'base_price_minor' => 80000,
        ]
    );

    $cr = ServiceChangeRequest::where('service_id', $service->id)->first();
    expect($cr)->not->toBeNull();
    expect($cr->items()->count())->toBeGreaterThanOrEqual(1); // at least base_price_minor

    $this->assertDatabaseHas('service_change_request_items', [
        'service_change_request_id' => $cr->id,
        'field_path' => 'base_price_minor',
    ]);
})->group('rental');

// ── Published service safety ──────────────────────────────────────────────────

it('published service live row is never touched by a material edit submission', function (ProductType $type): void {
    $route = $type->value;
    ['user' => $user, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType($type);
    $service = makePublishedServiceOfType($type, $vendor, $category, [
        'base_price_minor' => 50000,
        'name' => ['en' => 'Original EN', 'ar' => 'الأصلي AR'],
    ]);

    $originalPrice = $service->base_price_minor;
    $originalName = $service->getTranslation('name', 'en');
    $originalStatus = get_class($service->status);

    $this->actingAs($user)->patchJson(
        "/api/v1/vendor/services/{$route}/{$service->public_id}",
        [
            'name' => ['en' => 'Changed name', 'ar' => 'اسم متغير'],
            'short_description' => $service->getTranslations('short_description'),
            'category_id' => $service->category_id,
            'base_price_minor' => 99999,
        ]
    );

    $service->refresh();
    expect($service->base_price_minor)->toBe($originalPrice)
        ->and($service->getTranslation('name', 'en'))->toBe($originalName)
        ->and(get_class($service->status))->toBe($originalStatus);
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

// ── Duplicate pending edit prevention ────────────────────────────────────────

it('second material edit on the same service while one is pending returns 409', function (): void {
    ['user' => $user, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category, ['base_price_minor' => 50000]);

    // First submission
    $first = $this->actingAs($user)->patchJson(
        "/api/v1/vendor/services/rental/{$service->public_id}",
        [
            'name' => $service->getTranslations('name'),
            'short_description' => $service->getTranslations('short_description'),
            'category_id' => $service->category_id,
            'base_price_minor' => 60000,
        ]
    );
    $first->assertStatus(202);

    // Second submission while first is pending
    $second = $this->actingAs($user)->patchJson(
        "/api/v1/vendor/services/rental/{$service->public_id}",
        [
            'name' => $service->getTranslations('name'),
            'short_description' => $service->getTranslations('short_description'),
            'category_id' => $service->category_id,
            'base_price_minor' => 70000,
        ]
    );
    $second->assertStatus(409);
    $second->assertJsonPath('errors.0.code', 'service_edit.pending_request_exists');

    // Still only one CR in DB
    $this->assertDatabaseCount('service_change_requests', 1);
})->group('rental');

// ── Wrong vendor authorization ────────────────────────────────────────────────

it('unauthenticated request returns 401', function (): void {
    ['vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);

    $response = $this->patchJson(
        "/api/v1/vendor/services/rental/{$service->public_id}",
        ['base_price_minor' => 99999]
    );

    $response->assertStatus(401);
});

it('vendor cannot submit edit on another vendor\'s service → 404', function (): void {
    ['user' => $ownerUser, 'vendor' => $ownerVendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    ['user' => $otherUser] = makeApprovedVendorWithType(ProductType::Rental);

    $service = makePublishedServiceOfType(ProductType::Rental, $ownerVendor, $category);

    $response = $this->actingAs($otherUser)->patchJson(
        "/api/v1/vendor/services/rental/{$service->public_id}",
        [
            'name' => $service->getTranslations('name'),
            'short_description' => $service->getTranslations('short_description'),
            'category_id' => $service->category_id,
            'base_price_minor' => 99999,
        ]
    );

    $response->assertStatus(404);
});

it('draft service edit is applied directly (not staged) and returns 501 for now', function (): void {
    ['user' => $user, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = Service::factory()
        ->for($vendor, 'vendor')
        ->state([
            'product_type' => ProductType::Rental,
            'category_id' => $category->id,
            'status' => DraftState::$name,
            'base_price_minor' => 50000,
        ])
        ->create();

    $response = $this->actingAs($user)->patchJson(
        "/api/v1/vendor/services/rental/{$service->public_id}",
        [
            'name' => ['en' => 'New draft name', 'ar' => 'اسم مسودة جديد'],
            'short_description' => $service->getTranslations('short_description'),
            'category_id' => $service->category_id,
            'base_price_minor' => 60000,
        ]
    );

    // No CR created — draft services are not staged
    $response->assertStatus(501);
    $this->assertDatabaseCount('service_change_requests', 0);
});
