<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestApproved;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestClarificationReplied;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestClarificationRequested;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\Policies\ServiceEditApprovalPolicy;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\Event;

uses()->group('catalog', 'service-edit-approval', 'us4');

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);

    $this->admin = User::factory()->asAdmin()->create();
});

// ── US4: Admin requests clarification ────────────────────────────────────────

it('admin requests clarification → CR moves to awaiting_clarification + round increments', function (): void {
    Event::fake([ServiceChangeRequestClarificationRequested::class]);

    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/request-clarification",
        [
            'version' => 1,
            'admin_note' => [
                'en' => 'Please justify the price increase.',
                'ar' => 'يرجى تبرير رفع السعر.',
            ],
        ]
    );

    $response->assertStatus(200);
    $response->assertJsonPath('data.status', ServiceChangeRequestStatus::AwaitingClarification->value);

    $cr->refresh();
    expect($cr->status)->toBe(ServiceChangeRequestStatus::AwaitingClarification)
        ->and($cr->clarification_round)->toBe(1)
        ->and($cr->getTranslation('admin_note', 'en'))->toBe('Please justify the price increase.');

    // Admin message appended
    $this->assertDatabaseHas('service_change_request_messages', [
        'service_change_request_id' => $cr->id,
        'author_role' => 'admin',
        'clarification_round' => 1,
    ]);

    Event::assertDispatched(ServiceChangeRequestClarificationRequested::class);
})->group('rental');

it('admin requests clarification for sale service', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Sale);
    $service = makePublishedServiceOfType(ProductType::Sale, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/request-clarification",
        [
            'version' => 1,
            'admin_note' => ['en' => 'Clarify EN', 'ar' => 'وضح AR'],
        ]
    );

    $response->assertStatus(200);
    $cr->refresh();
    expect($cr->clarification_round)->toBe(1);
})->group('sale');

it('admin requests clarification for digital service', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Digital);
    $service = makePublishedServiceOfType(ProductType::Digital, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser);

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/request-clarification",
        [
            'version' => 1,
            'admin_note' => ['en' => 'Clarify EN', 'ar' => 'وضح AR'],
        ]
    );

    $response->assertStatus(200);
    $cr->refresh();
    expect($cr->status)->toBe(ServiceChangeRequestStatus::AwaitingClarification);
})->group('digital');

// ── Vendor replies to clarification ──────────────────────────────────────────

it('vendor replies to clarification → CR moves back to pending + vendor message appended', function (): void {
    Event::fake([ServiceChangeRequestClarificationReplied::class]);

    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);

    $cr = ServiceChangeRequest::factory()
        ->for($service, 'service')
        ->for($vendor, 'vendorProfile')
        ->awaitingClarification()
        ->state([
            'submitted_by' => $vendorUser->id,
            'clarification_round' => 1,
            'version' => 2,
        ])
        ->create();

    $response = $this->actingAs($vendorUser)->postJson(
        "/api/v1/vendor/service-change-requests/{$cr->public_id}/reply",
        [
            'body' => [
                'en' => 'The increase reflects higher raw material costs.',
                'ar' => 'تعكس الزيادة ارتفاع تكاليف المواد الخام.',
            ],
        ]
    );

    $response->assertStatus(200);
    $response->assertJsonPath('data.status', ServiceChangeRequestStatus::Pending->value);

    $cr->refresh();
    expect($cr->status)->toBe(ServiceChangeRequestStatus::Pending);

    $this->assertDatabaseHas('service_change_request_messages', [
        'service_change_request_id' => $cr->id,
        'author_role' => 'vendor',
        'clarification_round' => 1,
    ]);

    Event::assertDispatched(ServiceChangeRequestClarificationReplied::class);
})->group('rental');

it('vendor reply without Arabic body returns 422', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);

    $cr = ServiceChangeRequest::factory()
        ->for($service, 'service')
        ->for($vendor, 'vendorProfile')
        ->awaitingClarification()
        ->state(['submitted_by' => $vendorUser->id, 'version' => 2])
        ->create();

    $response = $this->actingAs($vendorUser)->postJson(
        "/api/v1/vendor/service-change-requests/{$cr->public_id}/reply",
        ['body' => ['en' => 'English only']] // missing ar
    );

    $response->assertStatus(422);
})->group('rental');

it('vendor reply on a non-awaiting CR returns 409', function (): void {
    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);
    $cr = openServiceChangeRequest($service, $vendor, $vendorUser); // status: pending

    $response = $this->actingAs($vendorUser)->postJson(
        "/api/v1/vendor/service-change-requests/{$cr->public_id}/reply",
        ['body' => ['en' => 'Reply', 'ar' => 'رد']]
    );

    $response->assertStatus(409);
})->group('rental');

// ── Clarification cap ─────────────────────────────────────────────────────────

it('requesting clarification beyond MAX_CLARIFICATIONS limit returns 403', function (): void {
    $maxRound = ServiceEditApprovalPolicy::MAX_CLARIFICATIONS;

    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category);

    // CR already at max clarification rounds
    $cr = ServiceChangeRequest::factory()
        ->for($service, 'service')
        ->for($vendor, 'vendorProfile')
        ->pending()
        ->state([
            'submitted_by' => $vendorUser->id,
            'clarification_round' => $maxRound,
            'version' => $maxRound + 1,
        ])
        ->create();

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/request-clarification",
        [
            'version' => $maxRound + 1,
            'admin_note' => ['en' => 'One more question', 'ar' => 'سؤال آخر'],
        ]
    );

    $response->assertStatus(403);
})->group('rental');

// ── Admin can approve after awaiting clarification ───────────────────────────

it('admin can approve a CR that is awaiting_clarification', function (): void {
    Event::fake([ServiceChangeRequestApproved::class]);

    ['user' => $vendorUser, 'vendor' => $vendor, 'category' => $category] = makeApprovedVendorWithType(ProductType::Rental);
    $service = makePublishedServiceOfType(ProductType::Rental, $vendor, $category, ['base_price_minor' => 50000]);

    $cr = ServiceChangeRequest::factory()
        ->for($service, 'service')
        ->for($vendor, 'vendorProfile')
        ->awaitingClarification()
        ->state([
            'submitted_by' => $vendorUser->id,
            'proposed_changes' => [
                'shared' => ['base_price_minor' => 60000],
                'type_specific' => [],
                'gallery_ops' => [],
                'availability_windows' => [],
                'excluded_dates' => [],
                'pricing_tiers' => [],
            ],
            'version' => 2,
        ])
        ->create();

    $response = $this->actingAs($this->admin)->postJson(
        "/api/v1/admin/service-change-requests/{$cr->public_id}/approve",
        ['version' => 2]
    );

    $response->assertStatus(200);
    $service->refresh();
    expect($service->base_price_minor)->toBe(60000);
    Event::assertDispatched(ServiceChangeRequestApproved::class);
})->group('rental');
