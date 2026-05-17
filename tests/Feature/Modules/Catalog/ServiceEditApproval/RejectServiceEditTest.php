<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestRejected;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\Event;

uses()->group('catalog', 'service-edit-approval', 'us3');

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);

    $this->admin = User::factory()->asAdmin()->create();
});

// ── US3: Admin rejects a pending CR ──────────────────────────────────────────

it('admin rejects rental service edit → 200 + live row unchanged', function (): void {
    Event::fake([ServiceChangeRequestRejected::class]);

    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category, ['base_price_minor' => 50000]);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/reject",
        [
            'version' => 1,
            'admin_note' => ['en' => 'Price increase too steep.', 'ar' => 'الزيادة في السعر كبيرة جداً.'],
        ]
    );

    $response->assertStatus(200);
    $response->assertJsonPath('data.status', ServiceChangeRequestStatus::Rejected->value);

    // Live row must remain unchanged
    $service->refresh();
    expect($service->base_price_minor)->toBe(50000);

    $cr->refresh();
    expect($cr->status)->toBe(ServiceChangeRequestStatus::Rejected)
        ->and($cr->decided_by)->toBe($this->admin->id)
        ->and($cr->getTranslation('admin_note', 'en'))->toBe('Price increase too steep.');

    Event::assertDispatched(ServiceChangeRequestRejected::class);
})->group('rental');

it('admin rejects sale service edit → 200 + live row unchanged', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Sale);
    $service = makePublishedServiceOfType(ProductType::Sale, $vendor, $category, ['base_price_minor' => 30000]);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/reject",
        [
            'version' => 1,
            'admin_note' => ['en' => 'Reject reason EN', 'ar' => 'سبب الرفض AR'],
        ]
    );

    $response->assertStatus(200);
    $service->refresh();
    expect($service->base_price_minor)->toBe(30000);
})->group('sale');

it('admin rejects digital service edit → 200 + live row unchanged', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Digital);
    $service = makePublishedServiceOfType(ProductType::Digital, $vendor, $category, ['base_price_minor' => 20000]);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/reject",
        [
            'version' => 1,
            'admin_note' => ['en' => 'Reject EN', 'ar' => 'رفض AR'],
        ]
    );

    $response->assertStatus(200);
    $service->refresh();
    expect($service->base_price_minor)->toBe(20000);
})->group('digital');

it('rejection without Arabic admin note returns 422', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/reject",
        [
            'version' => 1,
            'admin_note' => ['en' => 'English only note'], // missing AR
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['admin_note.ar']);
})->group('rental');

it('rejection without English admin note returns 422', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/reject",
        [
            'version' => 1,
            'admin_note' => ['ar' => 'Arabic only'], // missing EN
        ]
    );

    $response->assertStatus(422);
})->group('rental');

it('rejection without admin_note field returns 422', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/reject",
        ['version' => 1] // no admin_note
    );

    $response->assertStatus(422);
})->group('rental');

it('rejected CR is terminal — subsequent approval attempt returns 409', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);

    $cr = ServiceChangeRequest::factory()
        ->for($service, 'service')
        ->for($vendor, 'vendorProfile')
        ->rejected()
        ->state(['submitted_by' => $vendorUser->id, 'version' => 2])
        ->create();

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/approve",
        ['version' => 2]
    );

    $response->assertStatus(409);
})->group('rental');

it('vendor cannot reject their own CR → 403', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($vendorUser)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/reject",
        [
            'version' => 1,
            'admin_note' => ['en' => 'E', 'ar' => 'A'],
        ]
    );

    $response->assertStatus(403);
});
