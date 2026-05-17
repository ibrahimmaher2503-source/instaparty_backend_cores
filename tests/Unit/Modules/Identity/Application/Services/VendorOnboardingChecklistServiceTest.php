<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery;
use App\Modules\Identity\Application\Services\RequiredVendorDocumentTypesResolver;
use App\Modules\Identity\Application\Services\VendorOnboardingChecklistService;
use App\Modules\Identity\Domain\Enums\ChecklistItemStatus;
use App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Unit directory has TestCase applied in tests/Pest.php; only RefreshDatabase needs explicit opt-in
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a VendorOnboardingChecklistService with a controlled presence mock.
 *
 * @param  bool  $hasAny
 * @param  bool  $hasInReview
 */
function buildService(bool $hasAny = false, bool $hasInReview = false): VendorOnboardingChecklistService
{
    $mock = Mockery::mock(VendorServicePresenceQuery::class);
    $mock->allows('hasAnyService')->andReturn($hasAny);
    $mock->allows('hasServiceInReviewOrPublished')->andReturn($hasInReview);

    return new VendorOnboardingChecklistService(
        new RequiredVendorDocumentTypesResolver(),
        $mock,
    );
}

/**
 * Find a checklist item by its key from the DTO.
 */
function findItem(
    \App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistDTO $dto,
    VendorOnboardingChecklistItemKey $key,
): \App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistItemDTO {
    foreach ($dto->items as $item) {
        if ($item->key === $key) {
            return $item;
        }
    }
    throw new \RuntimeException("Item with key [{$key->value}] not found in checklist DTO.");
}

/**
 * Create a fully-populated individual VendorProfile.
 */
function individualVendor(array $overrides = []): VendorProfile
{
    return VendorProfile::factory()->create(array_merge([
        'business_type'          => 'individual',
        'business_name'          => ['en' => 'Test Vendor', 'ar' => 'بائع اختبار'],
        'primary_governorate_id' => \App\Modules\Geography\Domain\Models\Governorate::factory(),
        'primary_city_id'        => \App\Modules\Geography\Domain\Models\City::factory(),
        'address_line'           => ['en' => 'Test Street', 'ar' => 'شارع اختبار'],
        'national_id'            => '12345678901234',
        'approval_status'        => 'pending',
        'bank_name'              => null,
        'bank_account_holder'    => null,
        'bank_iban'              => null,
    ], $overrides));
}

/**
 * Create a fully-populated company VendorProfile.
 */
function companyVendor(array $overrides = []): VendorProfile
{
    return VendorProfile::factory()->create(array_merge([
        'business_type'           => 'company',
        'business_name'           => ['en' => 'Test Company', 'ar' => 'شركة اختبار'],
        'primary_governorate_id'  => \App\Modules\Geography\Domain\Models\Governorate::factory(),
        'primary_city_id'         => \App\Modules\Geography\Domain\Models\City::factory(),
        'address_line'            => ['en' => 'Test Street', 'ar' => 'شارع اختبار'],
        'commercial_register_no'  => 'CR-123456',
        'tax_id'                  => 'TX-654321',
        'approval_status'         => 'pending',
        'bank_name'               => null,
        'bank_account_holder'     => null,
        'bank_iban'               => null,
    ], $overrides));
}

/**
 * Create a fully-populated establishment VendorProfile.
 */
function establishmentVendor(array $overrides = []): VendorProfile
{
    return VendorProfile::factory()->create(array_merge([
        'business_type'          => 'establishment',
        'business_name'          => ['en' => 'Test Est', 'ar' => 'منشأة اختبار'],
        'primary_governorate_id' => \App\Modules\Geography\Domain\Models\Governorate::factory(),
        'primary_city_id'        => \App\Modules\Geography\Domain\Models\City::factory(),
        'address_line'           => ['en' => 'Test Street', 'ar' => 'شارع اختبار'],
        'commercial_register_no' => 'CR-999999',
        'approval_status'        => 'pending',
        'bank_name'              => null,
        'bank_account_holder'    => null,
        'bank_iban'              => null,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// T026 — Profile completeness per business_type
// ---------------------------------------------------------------------------

describe('T026 — Profile completeness per business_type', function () {
    it('marks profile complete when all required columns populated for individual', function () {
        $vendor = individualVendor();

        $dto    = buildService()->forVendor($vendor);
        $item   = findItem($dto, VendorOnboardingChecklistItemKey::Profile);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'profile');

    it('marks profile complete when all required columns populated for company', function () {
        $vendor = companyVendor();

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::Profile);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'profile');

    it('marks profile complete when all required columns populated for establishment', function () {
        $vendor = establishmentVendor();

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::Profile);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'profile');

    it('marks profile pending when business_name.en is empty', function () {
        $vendor = individualVendor([
            'business_name' => ['en' => '', 'ar' => 'بائع اختبار'],
        ]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::Profile);

        expect($item->status)->toBe(ChecklistItemStatus::Pending);
    })->group('onboarding-checklist', 'profile');

    it('marks profile pending when primary_governorate_id is null', function () {
        $vendor = individualVendor(['primary_governorate_id' => null]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::Profile);

        expect($item->status)->toBe(ChecklistItemStatus::Pending);
    })->group('onboarding-checklist', 'profile');
});

// ---------------------------------------------------------------------------
// T027 — Banking
// ---------------------------------------------------------------------------

describe('T027 — Banking', function () {
    it('marks banking complete when bank_name + bank_account_holder + bank_iban all set', function () {
        $vendor = VendorProfile::factory()->create([
            'approval_status'     => 'pending',
            'bank_name'           => 'CIB',
            'bank_account_holder' => 'Ibrahim Maher',
            'bank_iban'           => 'EG380019000500000000263180002',
        ]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::Banking);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'banking');

    it('marks banking pending when bank_iban is null', function () {
        $vendor = VendorProfile::factory()->create([
            'approval_status'     => 'pending',
            'bank_name'           => 'CIB',
            'bank_account_holder' => 'Ibrahim Maher',
            'bank_iban'           => null,
        ]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::Banking);

        expect($item->status)->toBe(ChecklistItemStatus::Pending);
    })->group('onboarding-checklist', 'banking');
});

// ---------------------------------------------------------------------------
// T028 — Docs uploaded
// ---------------------------------------------------------------------------

describe('T028 — Docs uploaded', function () {
    it('marks docs_uploaded complete when one row exists per required doc_type for individual', function () {
        $vendor = VendorProfile::factory()->create([
            'business_type'   => 'individual',
            'approval_status' => 'pending',
        ]);

        // Required types for individual: national_id, iban_proof
        VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
            'doc_type' => 'national_id',
            'status'   => 'pending',
        ]);
        VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
            'doc_type' => 'iban_proof',
            'status'   => 'pending',
        ]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::DocsUploaded);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'docs');

    it('marks docs_uploaded pending when no documents exist', function () {
        $vendor = VendorProfile::factory()->create([
            'business_type'   => 'individual',
            'approval_status' => 'pending',
        ]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::DocsUploaded);

        expect($item->status)->toBe(ChecklistItemStatus::Pending);
    })->group('onboarding-checklist', 'docs');
});

// ---------------------------------------------------------------------------
// T029 — Docs approved
// ---------------------------------------------------------------------------

describe('T029 — Docs approved', function () {
    it('marks docs_approved complete when all required docs are approved and not expired', function () {
        $vendor = VendorProfile::factory()->create([
            'business_type'   => 'individual',
            'approval_status' => 'pending',
        ]);

        VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
            'doc_type'  => 'national_id',
            'status'    => 'approved',
            'expires_at' => null,
        ]);
        VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
            'doc_type'  => 'iban_proof',
            'status'    => 'approved',
            'expires_at' => null,
        ]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::DocsApproved);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'docs');

    it('marks docs_approved warning when an approved doc has expired', function () {
        $vendor = VendorProfile::factory()->create([
            'business_type'   => 'individual',
            'approval_status' => 'pending',
        ]);

        VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
            'doc_type'  => 'national_id',
            'status'    => 'approved',
            'expires_at' => now()->subDay()->toDateString(), // expired yesterday
        ]);
        VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
            'doc_type'  => 'iban_proof',
            'status'    => 'approved',
            'expires_at' => null,
        ]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::DocsApproved);

        expect($item->status)->toBe(ChecklistItemStatus::Warning);
    })->group('onboarding-checklist', 'docs');

    it('marks docs_approved danger when any required doc is rejected', function () {
        $vendor = VendorProfile::factory()->create([
            'business_type'   => 'individual',
            'approval_status' => 'pending',
        ]);

        VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
            'doc_type' => 'national_id',
            'status'   => 'rejected',
        ]);
        VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
            'doc_type' => 'iban_proof',
            'status'   => 'approved',
        ]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::DocsApproved);

        expect($item->status)->toBe(ChecklistItemStatus::Danger);
    })->group('onboarding-checklist', 'docs');

    it('marks docs_approved info when any required doc is pending', function () {
        $vendor = VendorProfile::factory()->create([
            'business_type'   => 'individual',
            'approval_status' => 'pending',
        ]);

        VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
            'doc_type' => 'national_id',
            'status'   => 'pending',
        ]);
        VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
            'doc_type' => 'iban_proof',
            'status'   => 'approved',
        ]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::DocsApproved);

        expect($item->status)->toBe(ChecklistItemStatus::Info);
    })->group('onboarding-checklist', 'docs');
});

// ---------------------------------------------------------------------------
// T030 — Coverage / Hours / Service presence
// ---------------------------------------------------------------------------

describe('T030 — Coverage, Hours, Service presence', function () {
    it('marks coverage complete when at least one vendor_coverage_areas row exists', function () {
        $vendor = VendorProfile::factory()->create(['approval_status' => 'pending']);

        VendorCoverageArea::factory()->for($vendor, 'vendorProfile')->create();

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::Coverage);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'coverage');

    it('marks hours complete when at least one vendor_business_hours row exists', function () {
        $vendor = VendorProfile::factory()->create(['approval_status' => 'pending']);

        VendorBusinessHour::factory()->for($vendor, 'vendorProfile')->create();

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::Hours);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'hours');

    it('marks service_drafted complete when hasAnyService is true', function () {
        $vendor = VendorProfile::factory()->create(['approval_status' => 'pending']);

        $dto  = buildService(hasAny: true, hasInReview: false)->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::ServiceDrafted);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'service');

    it('marks service_submitted complete when hasServiceInReviewOrPublished is true', function () {
        $vendor = VendorProfile::factory()->create(['approval_status' => 'pending']);

        $dto  = buildService(hasAny: true, hasInReview: true)->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::ServiceSubmitted);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'service');
});

// ---------------------------------------------------------------------------
// T031 — Approval status
// ---------------------------------------------------------------------------

describe('T031 — Approval status', function () {
    it('marks approval_status complete when approval_status is approved', function () {
        $vendor = VendorProfile::factory()->approved()->create();

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::ApprovalStatus);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'approval-status');

    it('marks approval_status info when approval_status is pending', function () {
        $vendor = VendorProfile::factory()->pending()->create();

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::ApprovalStatus);

        expect($item->status)->toBe(ChecklistItemStatus::Info);
    })->group('onboarding-checklist', 'approval-status');

    it('marks approval_status warning when approval_status is changes_requested', function () {
        $vendor = VendorProfile::factory()->changesRequested()->create();

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::ApprovalStatus);

        expect($item->status)->toBe(ChecklistItemStatus::Warning);
    })->group('onboarding-checklist', 'approval-status');

    it('marks approval_status danger when approval_status is rejected', function () {
        $vendor = VendorProfile::factory()->rejected()->create();

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::ApprovalStatus);

        expect($item->status)->toBe(ChecklistItemStatus::Danger);
    })->group('onboarding-checklist', 'approval-status');
});

// ---------------------------------------------------------------------------
// T032 — Approved types
// ---------------------------------------------------------------------------

describe('T032 — Approved types', function () {
    it('marks approved_types complete when at least one approved type row exists', function () {
        $vendor = VendorProfile::factory()->approved()->create();

        VendorApprovedProductType::factory()->for($vendor, 'vendorProfile')->create([
            'revoked_at' => null,
        ]);

        $dto  = buildService()->forVendor($vendor);
        $item = findItem($dto, VendorOnboardingChecklistItemKey::ApprovedTypes);

        expect($item->status)->toBe(ChecklistItemStatus::Complete);
    })->group('onboarding-checklist', 'approved-types');
});

// ---------------------------------------------------------------------------
// T066 — Arabic locale labels
// ---------------------------------------------------------------------------

describe('T066 — Arabic locale labels', function () {
    it('returns Arabic-labelled items when app locale is ar', function () {
        $vendor = VendorProfile::factory()->create(['approval_status' => 'pending']);
        app()->setLocale('ar');
        $service = buildService(); // use the existing helper
        $dto = $service->forVendor($vendor);

        $profileItem = findItem($dto, VendorOnboardingChecklistItemKey::Profile);
        expect($profileItem->label)->toBe('الملف التجاري');

        $bankingItem = findItem($dto, VendorOnboardingChecklistItemKey::Banking);
        expect($bankingItem->label)->toBe('البيانات البنكية');
    })->group('onboarding-checklist', 'arabic', 't066');
});

// ---------------------------------------------------------------------------
// T033 — Next recommended action
// ---------------------------------------------------------------------------

describe('T033 — Next recommended action', function () {
    it('returns nextRecommendedAction as the first incomplete item in priority order', function () {
        // Profile is incomplete (no national_id), everything else is also pending.
        // Priority order: Profile → DocsUploaded → Banking → Coverage → Hours → ServiceDrafted → ServiceSubmitted
        $vendor = VendorProfile::factory()->create([
            'business_type'    => 'individual',
            'business_name'    => ['en' => '', 'ar' => ''],  // incomplete profile
            'approval_status'  => 'pending',
            'bank_name'        => null,
            'bank_account_holder' => null,
            'bank_iban'        => null,
        ]);

        $dto = buildService()->forVendor($vendor);

        expect($dto->nextRecommendedAction)->not->toBeNull();
        expect($dto->nextRecommendedAction->key)->toBe(VendorOnboardingChecklistItemKey::Profile);
    })->group('onboarding-checklist', 'next-action');

    it('returns null nextRecommendedAction when every priority-list item is complete', function () {
        // Build a vendor that has every actionable checklist item complete.
        $vendor = VendorProfile::factory()->create([
            'business_type'          => 'individual',
            'business_name'          => ['en' => 'Complete Vendor', 'ar' => 'بائع مكتمل'],
            'primary_governorate_id' => \App\Modules\Geography\Domain\Models\Governorate::factory(),
            'primary_city_id'        => \App\Modules\Geography\Domain\Models\City::factory(),
            'address_line'           => ['en' => 'Full St', 'ar' => 'شارع كامل'],
            'national_id'            => '12345678901234',
            'approval_status'        => 'approved',
            'bank_name'              => 'NBE',
            'bank_account_holder'    => 'Complete Vendor',
            'bank_iban'              => 'EG380019000500000000263180002',
        ]);

        // Docs — all required types uploaded and approved
        foreach (['national_id', 'iban_proof'] as $docType) {
            VendorDocument::factory()->for($vendor, 'vendorProfile')->create([
                'doc_type'  => $docType,
                'status'    => 'approved',
                'expires_at' => null,
            ]);
        }

        // Coverage and Hours
        VendorCoverageArea::factory()->for($vendor, 'vendorProfile')->create();
        VendorBusinessHour::factory()->for($vendor, 'vendorProfile')->create();

        // Services presence via mock (both true)
        $dto = buildService(hasAny: true, hasInReview: true)->forVendor($vendor);

        expect($dto->nextRecommendedAction)->toBeNull();
    })->group('onboarding-checklist', 'next-action');
});

// ---------------------------------------------------------------------------
// T050 (US2) — Rejection banner: rejectionState and rejectionReason (EN)
// ---------------------------------------------------------------------------

describe('T050 — Rejection banner state and reason', function () {
    it('populates rejectionState=Rejected and translated rejectionReason for approval_status=rejected', function () {
        $vendor = VendorProfile::factory()->create([
            'approval_status'  => 'rejected',
            'rejected_at'      => now(),
            'rejection_reason' => ['en' => 'Tax mismatch', 'ar' => 'عدم تطابق'],
        ]);

        app()->setLocale('en');

        $dto = buildService()->forVendor($vendor);

        expect($dto->rejectionState->value)->toBe('rejected');
        expect($dto->rejectionReason)->toBe('Tax mismatch');
    })->group('onboarding-checklist', 'rejection-banner', 't050');

    it('populates rejectionState=ChangesRequested for approval_status=changes_requested', function () {
        $vendor = VendorProfile::factory()->create([
            'approval_status'  => 'changes_requested',
            'rejection_reason' => ['en' => 'Fix address', 'ar' => 'صحح العنوان'],
        ]);

        $dto = buildService()->forVendor($vendor);

        expect($dto->rejectionState->value)->toBe('changes_requested');
    })->group('onboarding-checklist', 'rejection-banner', 't050');
});

// ---------------------------------------------------------------------------
// T051 (US2) — Rejection banner: AR locale returns Arabic rejection_reason
// ---------------------------------------------------------------------------

describe('T051 — Rejection reason respects app locale', function () {
    it('returns the AR rejection_reason when app locale is ar', function () {
        $vendor = VendorProfile::factory()->create([
            'approval_status'  => 'rejected',
            'rejected_at'      => now(),
            'rejection_reason' => ['en' => 'Tax mismatch', 'ar' => 'عدم تطابق'],
        ]);

        app()->setLocale('ar');

        $dto = buildService()->forVendor($vendor);

        expect($dto->rejectionReason)->toBe('عدم تطابق');
    })->group('onboarding-checklist', 'rejection-banner', 't051');
});

// ---------------------------------------------------------------------------
// T057 (US3) — Suspended vendor DTO
// ---------------------------------------------------------------------------

describe('T057 — Suspended vendor DTO', function () {
    it('returns isSuspended=true with empty items array for a suspended vendor', function () {
        $vendor = VendorProfile::factory()->create([
            'approval_status' => 'suspended',
            'suspended_at'    => now(),
        ]);

        $dto = buildService()->forVendor($vendor);

        expect($dto->isSuspended)->toBeTrue();
        expect($dto->items)->toBe([]);
    })->group('onboarding-checklist', 'suspended', 't057');

    it('populates suspendedAt and translated suspensionReason for a suspended vendor', function () {
        $vendor = VendorProfile::factory()->create([
            'approval_status'  => 'suspended',
            'suspended_at'     => now(),
            'rejection_reason' => ['en' => 'Multiple complaints', 'ar' => 'شكاوى متعددة'],
        ]);

        app()->setLocale('en');

        $dto = buildService()->forVendor($vendor);

        expect($dto->suspendedAt)->not->toBeNull();
        expect(str_contains((string) $dto->suspensionReason, 'complaint'))->toBeTrue();
    })->group('onboarding-checklist', 'suspended', 't057');
});

// ---------------------------------------------------------------------------
// T070 — Query budget: forVendor must issue no more than 6 queries (FR-EXT-017 / SC-005)
// ---------------------------------------------------------------------------

describe('T070 — Query budget', function () {
    it('issues no more than 6 queries per forVendor call for a non-suspended vendor', function () {
        $vendor = VendorProfile::factory()->create([
            'business_type'   => 'individual',
            'approval_status' => 'pending',
        ]);

        \Illuminate\Support\Facades\DB::enableQueryLog();

        buildService()->forVendor($vendor);

        $queryCount = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        expect($queryCount)->toBeLessThanOrEqual(6);
    })->group('onboarding-checklist', 'performance', 't070');
});
