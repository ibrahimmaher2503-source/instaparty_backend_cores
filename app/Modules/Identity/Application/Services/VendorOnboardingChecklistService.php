<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistDTO;
use App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistItemDTO;
use App\Modules\Identity\Domain\Enums\ChecklistItemStatus;
use App\Modules\Identity\Domain\Enums\RejectionState;
use App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Filament\Vendor\Pages\VendorAccountPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorBusinessHoursPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorCoverageAreasPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VendorOnboardingChecklistService
{
    /** Priority order for nextRecommendedAction (admin-decision rows excluded) */
    private const PRIORITY_KEYS = [
        VendorOnboardingChecklistItemKey::Profile,
        VendorOnboardingChecklistItemKey::DocsUploaded,
        VendorOnboardingChecklistItemKey::Banking,
        VendorOnboardingChecklistItemKey::Coverage,
        VendorOnboardingChecklistItemKey::Hours,
        VendorOnboardingChecklistItemKey::ServiceDrafted,
        VendorOnboardingChecklistItemKey::ServiceSubmitted,
    ];

    public function __construct(
        private readonly RequiredVendorDocumentTypesResolver $documentTypesResolver,
        private readonly VendorServicePresenceQuery $servicePresenceQuery,
    ) {}

    public function forVendor(VendorProfile $vendor): VendorOnboardingChecklistDTO
    {
        $approvalStatusValue = $vendor->approval_status instanceof \Spatie\ModelStates\State
            ? $vendor->approval_status->getValue()
            : (string) $vendor->approval_status;

        if ($approvalStatusValue === 'suspended') {
            return $this->buildSuspendedDTO($vendor);
        }

        // Load documents once for both docs-uploaded and docs-approved checks (query budget = 1)
        $requiredTypes = $this->documentTypesResolver->forBusinessType(
            $vendor->business_type instanceof \App\Modules\Identity\Domain\Enums\BusinessType
                ? $vendor->business_type->value
                : (string) $vendor->business_type
        );

        $latestDocsByType = $vendor->documents()
            ->whereIn('doc_type', $requiredTypes)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('doc_type')
            ->map(fn (Collection $docs) => $docs->first());

        $approvedTypeRows = $vendor->approvedTypes()->get();

        $items = [
            $this->profileItem($vendor),
            $this->bankingItem($vendor),
            $this->docsUploadedItem($vendor, $requiredTypes, $latestDocsByType),
            $this->docsApprovedItem($vendor, $requiredTypes, $latestDocsByType),
            $this->coverageItem($vendor),
            $this->hoursItem($vendor),
            $this->serviceDraftedItem($vendor),
            $this->serviceSubmittedItem($vendor),
            $this->approvalStatusItem($vendor, $approvalStatusValue),
            $this->approvedTypesItem($approvedTypeRows),
        ];

        $completedCount = count(array_filter(
            $items,
            fn (VendorOnboardingChecklistItemDTO $item) => $item->status === ChecklistItemStatus::Complete
        ));

        $itemsByKey = collect($items)->keyBy(fn ($item) => $item->key->value);

        $nextRecommendedAction = null;
        foreach (self::PRIORITY_KEYS as $key) {
            $item = $itemsByKey->get($key->value);
            if ($item && $item->status !== ChecklistItemStatus::Complete) {
                $nextRecommendedAction = $item;
                break;
            }
        }

        [$rejectionState, $rejectionReason] = $this->rejectionBannerFields($vendor, $approvalStatusValue);

        $approvedProductTypes = $approvedTypeRows->map(
            fn ($row) => $row->product_type instanceof ProductType
                ? $row->product_type
                : ProductType::from((string) $row->product_type)
        )->values()->all();

        return new VendorOnboardingChecklistDTO(
            vendorId: $vendor->id,
            isSuspended: false,
            suspendedAt: null,
            suspensionReason: null,
            rejectionState: $rejectionState,
            rejectionReason: $rejectionReason,
            items: $items,
            completedCount: $completedCount,
            totalCount: 10,
            progressPercent: intdiv($completedCount * 100, 10),
            nextRecommendedAction: $nextRecommendedAction,
            approvedProductTypes: $approvedProductTypes,
        );
    }

    private function buildSuspendedDTO(VendorProfile $vendor): VendorOnboardingChecklistDTO
    {
        $suspendedAt = $vendor->suspended_at
            ? CarbonImmutable::instance(Carbon::parse($vendor->suspended_at))
            : null;

        $suspensionReason = $vendor->getTranslation('suspension_reason', app()->getLocale(), false)
            ?: $vendor->getTranslation('rejection_reason', app()->getLocale(), false);

        return new VendorOnboardingChecklistDTO(
            vendorId: $vendor->id,
            isSuspended: true,
            suspendedAt: $suspendedAt,
            suspensionReason: $suspensionReason ?: null,
            rejectionState: RejectionState::None,
            rejectionReason: null,
            items: [],
            completedCount: 0,
            totalCount: 10,
            progressPercent: 0,
            nextRecommendedAction: null,
            approvedProductTypes: [],
        );
    }

    /** Resolves a Filament page/resource URL; returns null in test environments where panel routes are unregistered. */
    private function safeUrl(callable $resolver): ?string
    {
        try {
            return $resolver();
        } catch (RouteNotFoundException|\Illuminate\Routing\Exceptions\UrlGenerationException) {
            return null;
        }
    }

    // T038
    private function profileItem(VendorProfile $vendor): VendorOnboardingChecklistItemDTO
    {
        $businessType = $vendor->business_type instanceof \App\Modules\Identity\Domain\Enums\BusinessType
            ? $vendor->business_type
            : \App\Modules\Identity\Domain\Enums\BusinessType::tryFrom((string) $vendor->business_type);

        $complete = $this->isProfileComplete($vendor, $businessType);

        $url = $complete ? null : $this->safeUrl(fn () => VendorProfilePage::getUrl());

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::Profile,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.profile.label'),
            subText: null,
            url: $url,
        );
    }

    private function isProfileComplete(VendorProfile $vendor, ?\App\Modules\Identity\Domain\Enums\BusinessType $businessType): bool
    {
        $businessNameEn = is_array($vendor->business_name)
            ? ($vendor->business_name['en'] ?? '')
            : $vendor->getTranslation('business_name', 'en', false);
        $businessNameAr = is_array($vendor->business_name)
            ? ($vendor->business_name['ar'] ?? '')
            : $vendor->getTranslation('business_name', 'ar', false);

        $addressEn = is_array($vendor->address_line)
            ? ($vendor->address_line['en'] ?? '')
            : $vendor->getTranslation('address_line', 'en', false);
        $addressAr = is_array($vendor->address_line)
            ? ($vendor->address_line['ar'] ?? '')
            : $vendor->getTranslation('address_line', 'ar', false);

        if (
            empty($businessNameEn) || empty($businessNameAr) ||
            empty($businessType) ||
            empty($vendor->primary_governorate_id) ||
            empty($vendor->primary_city_id) ||
            empty($addressEn) || empty($addressAr)
        ) {
            return false;
        }

        return match ($businessType) {
            \App\Modules\Identity\Domain\Enums\BusinessType::Individual    => ! empty($vendor->national_id),
            \App\Modules\Identity\Domain\Enums\BusinessType::Company       => ! empty($vendor->commercial_register_no) && ! empty($vendor->tax_id),
            \App\Modules\Identity\Domain\Enums\BusinessType::Establishment => ! empty($vendor->commercial_register_no),
        };
    }

    // T039
    private function bankingItem(VendorProfile $vendor): VendorOnboardingChecklistItemDTO
    {
        $complete = ! empty($vendor->bank_name)
            && ! empty($vendor->bank_account_holder)
            && ! empty($vendor->bank_iban);

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::Banking,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.banking.label'),
            subText: null,
            url: $complete ? null : $this->safeUrl(fn () => VendorAccountPage::getUrl()),
        );
    }

    // T040
    private function docsUploadedItem(VendorProfile $vendor, array $requiredTypes, Collection $latestDocsByType): VendorOnboardingChecklistItemDTO
    {
        $complete = collect($requiredTypes)->every(fn (string $type) => $latestDocsByType->has($type));

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::DocsUploaded,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.docs_uploaded.label'),
            subText: null,
            url: $complete ? null : $this->safeUrl(fn () => VendorDocumentsPage::getUrl()),
        );
    }

    // T041 — reuses already-loaded $latestDocsByType (query budget stays at 1 for both docs checks)
    private function docsApprovedItem(VendorProfile $vendor, array $requiredTypes, Collection $latestDocsByType): VendorOnboardingChecklistItemDTO
    {
        $now = Carbon::now();
        $status = ChecklistItemStatus::Pending;

        foreach ($requiredTypes as $type) {
            $doc = $latestDocsByType->get($type);

            if (! $doc) {
                $status = ChecklistItemStatus::Pending;
                break;
            }

            $docStatus = $doc->status instanceof \App\Modules\Identity\Domain\Enums\DocumentStatus
                ? $doc->status->value
                : (string) $doc->status;

            if ($docStatus === 'rejected') {
                $status = ChecklistItemStatus::Danger;
                break;
            }

            if ($docStatus === 'pending') {
                $status = ChecklistItemStatus::Info;
                // don't break — a rejected doc takes precedence if found later
                continue;
            }

            if ($docStatus === 'approved') {
                if ($doc->expires_at !== null && Carbon::parse($doc->expires_at)->lte($now)) {
                    $status = ChecklistItemStatus::Warning;
                    continue;
                }
                // approved and not expired — this type is fine
                if ($status === ChecklistItemStatus::Pending) {
                    $status = ChecklistItemStatus::Complete;
                }
            }
        }

        // Re-scan for danger (highest priority) to ensure correct final status
        foreach ($requiredTypes as $type) {
            $doc = $latestDocsByType->get($type);
            if ($doc) {
                $ds = $doc->status instanceof \App\Modules\Identity\Domain\Enums\DocumentStatus
                    ? $doc->status->value
                    : (string) $doc->status;
                if ($ds === 'rejected') {
                    $status = ChecklistItemStatus::Danger;
                    break;
                }
            }
        }

        $url = $status === ChecklistItemStatus::Complete ? null : $this->safeUrl(fn () => VendorDocumentsPage::getUrl());

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::DocsApproved,
            status: $status,
            label: __('identity::vendor-onboarding.rows.docs_approved.label'),
            subText: null,
            url: $url,
        );
    }

    // T042
    private function coverageItem(VendorProfile $vendor): VendorOnboardingChecklistItemDTO
    {
        $complete = $vendor->coverageAreas()->exists();

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::Coverage,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.coverage.label'),
            subText: null,
            url: $complete ? null : $this->safeUrl(fn () => VendorCoverageAreasPage::getUrl()),
        );
    }

    // T043
    private function hoursItem(VendorProfile $vendor): VendorOnboardingChecklistItemDTO
    {
        $complete = $vendor->businessHours()->exists();

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::Hours,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.hours.label'),
            subText: null,
            url: $complete ? null : $this->safeUrl(fn () => VendorBusinessHoursPage::getUrl()),
        );
    }

    // T044
    private function serviceDraftedItem(VendorProfile $vendor): VendorOnboardingChecklistItemDTO
    {
        $complete = $this->servicePresenceQuery->hasAnyService($vendor->id);

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::ServiceDrafted,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.service_drafted.label'),
            subText: null,
            url: $complete ? null : $this->safeUrl(fn () => VendorRentalServiceResource::getUrl('index')),
        );
    }

    private function serviceSubmittedItem(VendorProfile $vendor): VendorOnboardingChecklistItemDTO
    {
        $complete = $this->servicePresenceQuery->hasServiceInReviewOrPublished($vendor->id);

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::ServiceSubmitted,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.service_submitted.label'),
            subText: null,
            url: $complete ? null : $this->safeUrl(fn () => VendorRentalServiceResource::getUrl('index')),
        );
    }

    // T045
    private function approvalStatusItem(VendorProfile $vendor, string $approvalStatusValue): VendorOnboardingChecklistItemDTO
    {
        $status = match ($approvalStatusValue) {
            'approved'          => ChecklistItemStatus::Complete,
            'pending'           => ChecklistItemStatus::Info,
            'changes_requested' => ChecklistItemStatus::Warning,
            'rejected'          => ChecklistItemStatus::Danger,
            default             => ChecklistItemStatus::Pending,
        };

        $subText = __('identity::vendor-onboarding.approval_status.' . $approvalStatusValue);

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::ApprovalStatus,
            status: $status,
            label: __('identity::vendor-onboarding.rows.approval_status.label'),
            subText: $subText,
            url: null,
        );
    }

    // T046
    private function approvedTypesItem(Collection $approvedTypeRows): VendorOnboardingChecklistItemDTO
    {
        $complete = $approvedTypeRows->isNotEmpty();

        $subText = null;
        if ($complete) {
            $typeLabels = $approvedTypeRows->map(function ($row) {
                $type = $row->product_type instanceof ProductType
                    ? $row->product_type
                    : ProductType::from((string) $row->product_type);

                return match ($type) {
                    ProductType::Rental  => __('identity::vendor-onboarding.approved_types.rental'),
                    ProductType::Sale    => __('identity::vendor-onboarding.approved_types.sale'),
                    ProductType::Digital => __('identity::vendor-onboarding.approved_types.digital'),
                };
            })->implode(', ');

            $subText = __('identity::vendor-onboarding.approved_types.sub_text', ['types' => $typeLabels]);
        }

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::ApprovedTypes,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.approved_types.label'),
            subText: $subText,
            url: null,
        );
    }

    // T054 (US2) — rejection banner fields
    private function rejectionBannerFields(VendorProfile $vendor, string $approvalStatusValue): array
    {
        $rejectionState = match ($approvalStatusValue) {
            'rejected'          => RejectionState::Rejected,
            'changes_requested' => RejectionState::ChangesRequested,
            default             => RejectionState::None,
        };

        $rejectionReason = $rejectionState !== RejectionState::None
            ? ($vendor->getTranslation('rejection_reason', app()->getLocale(), false) ?: null)
            : null;

        return [$rejectionState, $rejectionReason];
    }
}
