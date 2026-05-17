<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Shared\Domain\Models\ChangeRequestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('catalog', 'service-changes');

beforeEach(function () {
    $this->admin = User::factory()->asAdmin()->create();
    $this->vendor = User::factory()->asVendor()->create();
    $this->vendorProfile = VendorProfile::factory()
        ->for($this->vendor, 'user')
        ->state(['approval_status' => ApprovalStatus::Approved])
        ->create();
});

describe('Request service changes', function () {
    it('requests rental service changes as admin', function () {
        $this->actingAs($this->admin);

        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Rental,
                'status' => ServiceStatus::PendingReview,
            ]);

        $this->assertDatabaseHas('services', ['id' => $service->id]);

        $response = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'rental_duration',
                    'requested_change_en' => 'Minimum rental must be 4 hours',
                    'requested_change_ar' => 'يجب أن تكون مدة الإيجار الحد الأدنى 4 ساعات',
                ],
            ],
        ], [
            'Idempotency-Key' => 'test-key-1',
        ]);

        $response->assertStatus(201);
        expect($response->json('data.subject_type'))->toBe('service');
        expect($response->json('data.cycle_number'))->toBe(1);

        $changeRequest = ChangeRequest::where('subject_id', $service->id)
            ->where('subject_type', 'service')
            ->first();

        expect($changeRequest)->not->toBeNull();
        expect($changeRequest->status)->toBe(ChangeRequestStatus::Open);
        expect($changeRequest->cycle_number)->toBe(1);
        expect($changeRequest->items()->count())->toBe(1);

        $service->refresh();
        expect($service->status)->toBe(ServiceStatus::ChangesRequested);
    })->group('rental');

    it('requests sale service changes as admin', function () {
        $this->actingAs($this->admin);

        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Sale,
                'status' => ServiceStatus::PendingReview,
            ]);

        $response = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'lead_time',
                    'requested_change_en' => 'Lead time must be at least 24 hours',
                    'requested_change_ar' => 'يجب أن يكون وقت الانتظار 24 ساعة على الأقل',
                ],
            ],
        ], [
            'Idempotency-Key' => 'test-key-2',
        ]);

        $response->assertStatus(201);
        $changeRequest = ChangeRequest::where('subject_id', $service->id)->first();
        expect($changeRequest->items()->first()->requested_change_en)
            ->toBe('Lead time must be at least 24 hours');
    })->group('sale');

    it('requests digital service changes as admin', function () {
        $this->actingAs($this->admin);

        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Digital,
                'status' => ServiceStatus::PendingReview,
            ]);

        $response = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'delivery_method',
                    'requested_change_en' => 'Must support email delivery',
                    'requested_change_ar' => 'يجب أن يدعم البريد الإلكتروني',
                ],
            ],
        ], [
            'Idempotency-Key' => 'test-key-3',
        ]);

        $response->assertStatus(201);
        $changeRequest = ChangeRequest::where('subject_id', $service->id)->first();
        expect($changeRequest->subject_type)->toBe('service');
    })->group('digital');

    it('rejects missing idempotency key', function () {
        $this->actingAs($this->admin);

        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Rental,
                'status' => ServiceStatus::PendingReview,
            ]);

        $response = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'rental_duration',
                    'requested_change_en' => 'Test',
                    'requested_change_ar' => 'اختبار',
                ],
            ],
        ]);

        $response->assertStatus(400);
    });

    it('enforces bilingual change descriptions', function () {
        $this->actingAs($this->admin);

        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Rental,
                'status' => ServiceStatus::PendingReview,
            ]);

        $response = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'rental_duration',
                    'requested_change_en' => 'English description',
                    // Missing Arabic description
                ],
            ],
        ], [
            'Idempotency-Key' => 'test-key-4',
        ]);

        $response->assertStatus(422);
        expect($response->json('errors.items.0.requested_change_ar'))->not->toBeEmpty();
    });

    it('rejects request for non-actionable service status', function () {
        $this->actingAs($this->admin);

        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Rental,
                'status' => ServiceStatus::Published,
            ]);

        $response = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'rental_duration',
                    'requested_change_en' => 'Test',
                    'requested_change_ar' => 'اختبار',
                ],
            ],
        ], [
            'Idempotency-Key' => 'test-key-5',
        ]);

        $response->assertStatus(422);
    });

    it('requires admin authentication', function () {
        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Rental,
                'status' => ServiceStatus::PendingReview,
            ]);

        $this->actingAs(User::factory()->asVendor()->create());

        $response = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'rental_duration',
                    'requested_change_en' => 'Test',
                    'requested_change_ar' => 'اختبار',
                ],
            ],
        ], [
            'Idempotency-Key' => 'test-key-6',
        ]);

        $response->assertStatus(403);
    });
});

describe('Vendor resubmit after changes', function () {
    it('vendor resubmits rental service after requested changes', function () {
        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Rental,
                'status' => ServiceStatus::ChangesRequested,
            ]);

        $changeRequest = ChangeRequest::create([
            'subject_type' => 'service',
            'subject_id' => $service->id,
            'requested_by_admin_id' => $this->admin->id,
            'status' => 'open',
            'cycle_number' => 1,
        ]);

        ChangeRequestItem::create([
            'change_request_id' => $changeRequest->id,
            'field_path' => 'rental_duration',
            'current_value_snapshot' => '2',
            'requested_change_en' => 'Must be at least 4 hours',
            'requested_change_ar' => 'يجب أن تكون 4 ساعات على الأقل',
            'item_status' => 'pending',
        ]);

        $this->actingAs($this->vendor);

        $response = $this->postJson("/api/v1/vendor/services/{$service->public_id}/resubmit", [
            'changed_fields' => [
                'rental_duration' => 4,
            ],
        ], [
            'Idempotency-Key' => 'resubmit-key-1',
        ]);

        $response->assertStatus(200);
        expect($response->json('data.status'))->toBe('resubmitted');
        expect($response->json('data.cycle_number'))->toBe(1);

        $changeRequest->refresh();
        expect($changeRequest->status)->toBe(ChangeRequestStatus::Resubmitted);

        $changeRequestItem = $changeRequest->items()->first();
        expect($changeRequestItem->item_status)->toBe('addressed');

        $service->refresh();
        expect($service->status)->toBe(ServiceStatus::PendingReview);
    })->group('rental');

    it('vendor resubmits sale service after requested changes', function () {
        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Sale,
                'status' => ServiceStatus::ChangesRequested,
            ]);

        $changeRequest = ChangeRequest::create([
            'subject_type' => 'service',
            'subject_id' => $service->id,
            'requested_by_admin_id' => $this->admin->id,
            'status' => 'open',
            'cycle_number' => 1,
        ]);

        ChangeRequestItem::create([
            'change_request_id' => $changeRequest->id,
            'field_path' => 'lead_time_hours',
            'current_value_snapshot' => '4',
            'requested_change_en' => 'Lead time must be 24 hours',
            'requested_change_ar' => 'وقت الانتظار يجب أن يكون 24 ساعة',
            'item_status' => 'pending',
        ]);

        $this->actingAs($this->vendor);

        $response = $this->postJson("/api/v1/vendor/services/{$service->public_id}/resubmit", [
            'changed_fields' => [
                'lead_time_hours' => 24,
            ],
        ], [
            'Idempotency-Key' => 'resubmit-key-2',
        ]);

        $response->assertStatus(200);
        expect($response->json('data.status'))->toBe('resubmitted');
    })->group('sale');

    it('vendor resubmits digital service after requested changes', function () {
        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Digital,
                'status' => ServiceStatus::ChangesRequested,
            ]);

        $changeRequest = ChangeRequest::create([
            'subject_type' => 'service',
            'subject_id' => $service->id,
            'requested_by_admin_id' => $this->admin->id,
            'status' => 'open',
            'cycle_number' => 1,
        ]);

        ChangeRequestItem::create([
            'change_request_id' => $changeRequest->id,
            'field_path' => 'delivery_method',
            'current_value_snapshot' => 'link',
            'requested_change_en' => 'Add email delivery',
            'requested_change_ar' => 'أضف توصيل البريد الإلكتروني',
            'item_status' => 'pending',
        ]);

        $this->actingAs($this->vendor);

        $response = $this->postJson("/api/v1/vendor/services/{$service->public_id}/resubmit", [
            'changed_fields' => [
                'delivery_method' => 'email',
            ],
        ], [
            'Idempotency-Key' => 'resubmit-key-3',
        ]);

        $response->assertStatus(200);
        expect($response->json('data.status'))->toBe('resubmitted');
    })->group('digital');

    it('rejects resubmit without open change request', function () {
        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Rental,
                'status' => ServiceStatus::PendingReview,
            ]);

        $this->actingAs($this->vendor);

        $response = $this->postJson("/api/v1/vendor/services/{$service->public_id}/resubmit", [
            'changed_fields' => [
                'rental_duration' => 4,
            ],
        ], [
            'Idempotency-Key' => 'resubmit-key-4',
        ]);

        $response->assertStatus(422);
    });

    it('requires vendor authentication', function () {
        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Rental,
                'status' => ServiceStatus::ChangesRequested,
            ]);

        ChangeRequest::create([
            'subject_type' => 'service',
            'subject_id' => $service->id,
            'requested_by_admin_id' => $this->admin->id,
            'status' => 'open',
            'cycle_number' => 1,
        ]);

        $response = $this->postJson("/api/v1/vendor/services/{$service->public_id}/resubmit", [
            'changed_fields' => [
                'rental_duration' => 4,
            ],
        ], [
            'Idempotency-Key' => 'resubmit-key-5',
        ]);

        $response->assertStatus(401);
    });
});

describe('3-cycle limit enforcement', function () {
    it('allows up to 3 change request cycles for rental service', function () {
        $this->actingAs($this->admin);

        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Rental,
                'status' => ServiceStatus::PendingReview,
            ]);

        // Cycle 1: Request changes
        $response1 = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'rental_duration',
                    'requested_change_en' => 'Cycle 1',
                    'requested_change_ar' => 'الدورة 1',
                ],
            ],
        ], [
            'Idempotency-Key' => 'cycle-1-key',
        ]);
        expect($response1->status())->toBe(201);
        $service->refresh();
        expect($service->status)->toBe(ServiceStatus::ChangesRequested);

        // Cycle 1: Vendor resubmits
        $changeRequest = ChangeRequest::where('subject_id', $service->id)->first();
        $this->actingAs($this->vendor);
        $this->postJson("/api/v1/vendor/services/{$service->public_id}/resubmit", [
            'changed_fields' => ['rental_duration' => 4],
        ], ['Idempotency-Key' => 'resubmit-1']);

        // Cycle 2: Request changes again
        $this->actingAs($this->admin);
        $response2 = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'rental_duration',
                    'requested_change_en' => 'Cycle 2',
                    'requested_change_ar' => 'الدورة 2',
                ],
            ],
        ], [
            'Idempotency-Key' => 'cycle-2-key',
        ]);
        expect($response2->status())->toBe(201);

        // Cycle 2: Vendor resubmits
        $this->actingAs($this->vendor);
        $this->postJson("/api/v1/vendor/services/{$service->public_id}/resubmit", [
            'changed_fields' => ['rental_duration' => 6],
        ], ['Idempotency-Key' => 'resubmit-2']);

        // Cycle 3: Request changes one more time
        $this->actingAs($this->admin);
        $response3 = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'rental_duration',
                    'requested_change_en' => 'Cycle 3',
                    'requested_change_ar' => 'الدورة 3',
                ],
            ],
        ], [
            'Idempotency-Key' => 'cycle-3-key',
        ]);
        expect($response3->status())->toBe(201);

        // Cycle 3: Vendor resubmits
        $this->actingAs($this->vendor);
        $this->postJson("/api/v1/vendor/services/{$service->public_id}/resubmit", [
            'changed_fields' => ['rental_duration' => 8],
        ], ['Idempotency-Key' => 'resubmit-3']);

        // Cycle 4: Should be blocked
        $this->actingAs($this->admin);
        $response4 = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'rental_duration',
                    'requested_change_en' => 'Cycle 4',
                    'requested_change_ar' => 'الدورة 4',
                ],
            ],
        ], [
            'Idempotency-Key' => 'cycle-4-key',
        ]);
        expect($response4->status())->toBe(422);
    })->group('rental');

    it('blocks 4th change request cycle for sale service', function () {
        $this->actingAs($this->admin);

        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Sale,
                'status' => ServiceStatus::PendingReview,
            ]);

        // Create existing change request with cycle_number = 3
        $changeRequest = ChangeRequest::create([
            'subject_type' => 'service',
            'subject_id' => $service->id,
            'requested_by_admin_id' => $this->admin->id,
            'status' => 'resubmitted',
            'cycle_number' => 3,
        ]);

        $service->update(['status' => ServiceStatus::PendingReview]);

        $response = $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'lead_time',
                    'requested_change_en' => 'Try cycle 4',
                    'requested_change_ar' => 'جرب الدورة 4',
                ],
            ],
        ], [
            'Idempotency-Key' => 'cycle-4-blocked',
        ]);

        $response->assertStatus(422);
        expect($response->json('message'))->toContain('max');
    })->group('sale');
});

describe('Bilingual checklist persistence', function () {
    it('persists bilingual change descriptions exactly', function () {
        $this->actingAs($this->admin);

        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Rental,
                'status' => ServiceStatus::PendingReview,
            ]);

        $enDescription = 'The rental duration must be at least 4 hours for safety setup';
        $arDescription = 'يجب أن تكون مدة الإيجار 4 ساعات على الأقل لإعداد السلامة';

        $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'rental_duration',
                    'requested_change_en' => $enDescription,
                    'requested_change_ar' => $arDescription,
                ],
            ],
        ], [
            'Idempotency-Key' => 'bilingual-test',
        ]);

        $item = ChangeRequestItem::query()
            ->whereRelation('changeRequest', 'subject_id', $service->id)
            ->first();

        expect($item->requested_change_en)->toBe($enDescription);
        expect($item->requested_change_ar)->toBe($arDescription);
    });

    it('maintains change history through multiple cycles', function () {
        $this->actingAs($this->admin);

        $service = Service::factory()
            ->for($this->vendorProfile, 'vendor')
            ->create([
                'product_type' => ProductType::Digital,
                'status' => ServiceStatus::PendingReview,
            ]);

        // Cycle 1
        $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'delivery_method',
                    'requested_change_en' => 'Add email support',
                    'requested_change_ar' => 'إضافة دعم البريد الإلكتروني',
                ],
            ],
        ], [
            'Idempotency-Key' => 'history-cycle-1',
        ]);

        $cr1 = ChangeRequest::where('subject_id', $service->id)->first();
        expect($cr1->cycle_number)->toBe(1);
        expect($cr1->items()->count())->toBe(1);

        // Resubmit and request again
        $this->actingAs($this->vendor);
        $this->postJson("/api/v1/vendor/services/{$service->public_id}/resubmit", [
            'changed_fields' => ['delivery_method' => 'email'],
        ], ['Idempotency-Key' => 'history-resubmit-1']);

        $this->actingAs($this->admin);
        $this->postJson("/api/v1/admin/services/{$service->public_id}/request-changes", [
            'items' => [
                [
                    'field_path' => 'expiry_days',
                    'requested_change_en' => 'Set expiry to 30 days',
                    'requested_change_ar' => 'تعيين الانتهاء إلى 30 يومًا',
                ],
            ],
        ], [
            'Idempotency-Key' => 'history-cycle-2',
        ]);

        $cr2 = ChangeRequest::where('subject_id', $service->id)->where('cycle_number', 2)->first();
        expect($cr2)->not->toBeNull();
        expect($cr2->items()->first()->field_path)->toBe('expiry_days');

        // Both change requests should exist
        $allCR = ChangeRequest::where('subject_id', $service->id)->get();
        expect($allCR->count())->toBe(2);
    });
});
