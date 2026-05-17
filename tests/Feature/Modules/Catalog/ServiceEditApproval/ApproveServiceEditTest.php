<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestApproved;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\Event;

uses()->group('catalog', 'service-edit-approval', 'us2');

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);

    $this->admin = User::factory()->asAdmin()->create();
});

// ── US2: Admin approves a pending CR ─────────────────────────────────────────

it('admin approves rental service edit → 200 + live row updated to proposed values', function (): void {
    Event::fake([ServiceChangeRequestApproved::class]);

    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category, ['base_price_minor' => 50000]);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/approve",
        ['version' => 1]
    );

    $response->assertStatus(200);
    $response->assertJsonPath('data.status', ServiceChangeRequestStatus::Approved->value);

    // Live row must now reflect proposed values
    $service->refresh();
    expect($service->base_price_minor)->toBe(60000);

    // CR marked approved
    $cr->refresh();
    expect($cr->status)->toBe(ServiceChangeRequestStatus::Approved)
        ->and($cr->decided_by)->toBe($this->admin->id)
        ->and($cr->decided_at)->not->toBeNull();

    Event::assertDispatched(ServiceChangeRequestApproved::class);
})->group('rental');

it('admin approves sale service edit → 200 + live row updated', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Sale);
    $service = makePublishedServiceOfType(ProductType::Sale, $vendor, $category, ['base_price_minor' => 30000]);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/approve",
        ['version' => 1]
    );

    $response->assertStatus(200);
    $service->refresh();
    expect($service->base_price_minor)->toBe(60000);
})->group('sale');

it('admin approves digital service edit → 200 + live row updated', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Digital);
    $service = makePublishedServiceOfType(ProductType::Digital, $vendor, $category, ['base_price_minor' => 20000]);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/approve",
        ['version' => 1]
    );

    $response->assertStatus(200);
    $service->refresh();
    expect($service->base_price_minor)->toBe(60000);
})->group('digital');

it('admin can approve with optional bilingual note', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/approve",
        [
            'version' => 1,
            'admin_note' => ['en' => 'Approved — pricing looks fair.', 'ar' => 'تمت الموافقة — التسعير معقول.'],
        ]
    );

    $response->assertStatus(200);
    $cr->refresh();
    expect($cr->getTranslations('admin_note'))->toBeArray()
        ->and($cr->getTranslation('admin_note', 'en'))->toBe('Approved — pricing looks fair.');
})->group('rental');

// ── Version conflict (optimistic lock) ───────────────────────────────────────

it('stale version number returns 409 conflict', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/approve",
        ['version' => 999] // stale
    );

    $response->assertStatus(409);
    $response->assertJsonPath('errors.0.code', 'service_change_request.version_mismatch');

    // Live row unchanged
    $service->refresh();
    expect($service->base_price_minor)->toBe(50000);
})->group('rental');

it('cannot approve an already-approved CR', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);

    $cr = ServiceChangeRequest::factory()
        ->for($service, 'service')
        ->for($vendor, 'vendorProfile')
        ->approved()
        ->state(['submitted_by' => $vendorUser->id, 'version' => 2])
        ->create();

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/approve",
        ['version' => 2] // matches, but status is terminal
    );

    $response->assertStatus(409);
})->group('rental');

// ── Permission checks ─────────────────────────────────────────────────────────

it('unauthenticated request to approve returns 401', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/approve",
        ['version' => 1]
    );

    $response->assertStatus(401);
});

it('vendor cannot approve their own CR → 403', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($vendorUser)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/approve",
        ['version' => 1]
    );

    $response->assertStatus(403);
});
