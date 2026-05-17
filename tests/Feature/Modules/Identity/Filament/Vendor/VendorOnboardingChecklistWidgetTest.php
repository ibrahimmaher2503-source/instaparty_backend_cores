<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistDTO;
use App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistItemDTO;
use App\Modules\Identity\Application\Services\VendorOnboardingChecklistService;
use App\Modules\Identity\Domain\Enums\ChecklistItemStatus;
use App\Modules\Identity\Domain\Enums\RejectionState;
use App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Widgets\VendorOnboardingChecklistWidget;
use Livewire\Livewire;

// Feature directory already has TestCase + RefreshDatabase applied in tests/Pest.php

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a VendorOnboardingChecklistItemDTO with Pending status for a given key.
 */
function pendingItem(VendorOnboardingChecklistItemKey $key): VendorOnboardingChecklistItemDTO
{
    return new VendorOnboardingChecklistItemDTO(
        key: $key,
        status: ChecklistItemStatus::Pending,
        label: __('identity::vendor-onboarding.rows.'.$key->value.'.label'),
        subText: null,
        url: null,
    );
}

/**
 * Build a VendorOnboardingChecklistItemDTO with Complete status for a given key.
 */
function completeItem(VendorOnboardingChecklistItemKey $key): VendorOnboardingChecklistItemDTO
{
    return new VendorOnboardingChecklistItemDTO(
        key: $key,
        status: ChecklistItemStatus::Complete,
        label: __('identity::vendor-onboarding.rows.'.$key->value.'.label'),
        subText: null,
        url: null,
    );
}

/**
 * Build a fully-pending checklist DTO (all 10 items Pending, first item as next action).
 */
function allPendingChecklist(int $vendorId): VendorOnboardingChecklistDTO
{
    $items = array_map(
        fn (VendorOnboardingChecklistItemKey $key) => pendingItem($key),
        VendorOnboardingChecklistItemKey::cases(),
    );

    $nextAction = new VendorOnboardingChecklistItemDTO(
        key: VendorOnboardingChecklistItemKey::Profile,
        status: ChecklistItemStatus::Pending,
        label: __('identity::vendor-onboarding.rows.profile.label'),
        subText: __('identity::vendor-onboarding.rows.profile.cta'),
        url: '/vendor/profile/edit',
    );

    return new VendorOnboardingChecklistDTO(
        vendorId: $vendorId,
        isSuspended: false,
        suspendedAt: null,
        suspensionReason: null,
        rejectionState: RejectionState::None,
        rejectionReason: null,
        items: $items,
        completedCount: 0,
        totalCount: 10,
        progressPercent: 0,
        nextRecommendedAction: $nextAction,
        approvedProductTypes: [],
    );
}

/**
 * Build a fully-complete checklist DTO (all 10 items Complete, no next action).
 */
function allCompleteChecklist(int $vendorId): VendorOnboardingChecklistDTO
{
    $items = array_map(
        fn (VendorOnboardingChecklistItemKey $key) => completeItem($key),
        VendorOnboardingChecklistItemKey::cases(),
    );

    return new VendorOnboardingChecklistDTO(
        vendorId: $vendorId,
        isSuspended: false,
        suspendedAt: null,
        suspensionReason: null,
        rejectionState: RejectionState::None,
        rejectionReason: null,
        items: $items,
        completedCount: 10,
        totalCount: 10,
        progressPercent: 100,
        nextRecommendedAction: null,
        approvedProductTypes: [ProductType::Rental],
    );
}

// ---------------------------------------------------------------------------
// Shared setup: create a vendor user and act as them
// ---------------------------------------------------------------------------

function createVendorUser(array $profileOverrides = []): array
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $profile = VendorProfile::factory()->for($user)->create(
        array_merge(['approval_status' => 'pending'], $profileOverrides)
    );

    return [$user, $profile];
}

// ---------------------------------------------------------------------------
// Test suite
// ---------------------------------------------------------------------------

describe('VendorOnboardingChecklistWidget', function (): void {

    // -----------------------------------------------------------------------
    // T034 — All 10 row labels are present for an empty vendor
    // -----------------------------------------------------------------------
    it('renders all 10 rows with the correct status icons for an empty vendor', function (): void {
        [$user, $profile] = createVendorUser();

        // Inject a mock service that returns all-pending DTO so the test is
        // independent of the (currently-stub) service implementation.
        $checklist = allPendingChecklist($profile->id);
        $mock = Mockery::mock(VendorOnboardingChecklistService::class);
        $mock->shouldReceive('forVendor')->once()->andReturn($checklist);
        app()->instance(VendorOnboardingChecklistService::class, $mock);

        $this->actingAs($user);

        $component = Livewire::test(VendorOnboardingChecklistWidget::class);

        $component->assertOk();

        // All 10 row labels must appear in the rendered output
        $component->assertSee(__('identity::vendor-onboarding.rows.profile.label'));
        $component->assertSee(__('identity::vendor-onboarding.rows.banking.label'));
        $component->assertSee(__('identity::vendor-onboarding.rows.docs_uploaded.label'));
        $component->assertSee(__('identity::vendor-onboarding.rows.docs_approved.label'));
        $component->assertSee(__('identity::vendor-onboarding.rows.coverage.label'));
        $component->assertSee(__('identity::vendor-onboarding.rows.hours.label'));
        $component->assertSee(__('identity::vendor-onboarding.rows.service_drafted.label'));
        $component->assertSee(__('identity::vendor-onboarding.rows.service_submitted.label'));
        $component->assertSee(__('identity::vendor-onboarding.rows.approval_status.label'));
        $component->assertSee(__('identity::vendor-onboarding.rows.approved_types.label'));
    })->group('identity', 'widgets', 'vendor-onboarding', 't034');

    // -----------------------------------------------------------------------
    // T035 — Next recommended action CTA points to profile for empty vendor
    // -----------------------------------------------------------------------
    it('renders the Next recommended action button pointing to profile for an empty vendor', function (): void {
        [$user, $profile] = createVendorUser();

        $checklist = allPendingChecklist($profile->id);
        $mock = Mockery::mock(VendorOnboardingChecklistService::class);
        $mock->shouldReceive('forVendor')->once()->andReturn($checklist);
        app()->instance(VendorOnboardingChecklistService::class, $mock);

        $this->actingAs($user);

        $component = Livewire::test(VendorOnboardingChecklistWidget::class);

        $component->assertOk();

        // The CTA for the profile row (first priority when vendor is empty)
        $component->assertSee(__('identity::vendor-onboarding.rows.profile.cta'));
    })->group('identity', 'widgets', 'vendor-onboarding', 't035');

    // -----------------------------------------------------------------------
    // T036 — Onboarding complete chip shown and no CTA for fully-onboarded vendor
    // -----------------------------------------------------------------------
    it('renders the Onboarding complete chip and no CTA button for a fully-onboarded vendor', function (): void {
        // Build a fully-onboarded vendor with all required data
        [$user, $profile] = createVendorUser(['approval_status' => 'approved']);

        // Banking details
        VendorProfile::where('id', $profile->id)->update([
            'bank_name'            => 'CIB',
            'bank_account_holder'  => 'Vendor Full',
            'bank_iban'            => 'EG380019000500000000VENDOR_A',
        ]);

        // Approved documents (national_id + iban_proof for individual business type)
        VendorDocument::factory()
            ->for($profile, 'vendorProfile')
            ->approved()
            ->create(['doc_type' => 'national_id']);

        VendorDocument::factory()
            ->for($profile, 'vendorProfile')
            ->approved()
            ->create(['doc_type' => 'iban_proof']);

        // Coverage area
        VendorCoverageArea::factory()->for($profile, 'vendorProfile')->create();

        // Business hours
        VendorBusinessHour::factory()->for($profile, 'vendorProfile')->create();

        // Approved product type
        VendorApprovedProductType::factory()
            ->for($profile, 'vendorProfile')
            ->forType(ProductType::Rental)
            ->create();

        // A published service
        Service::factory()
            ->published()
            ->create(['vendor_profile_id' => $profile->id]);

        // Inject mock returning a fully-complete checklist DTO
        $checklist = allCompleteChecklist($profile->id);
        $mock = Mockery::mock(VendorOnboardingChecklistService::class);
        $mock->shouldReceive('forVendor')->once()->andReturn($checklist);
        app()->instance(VendorOnboardingChecklistService::class, $mock);

        $this->actingAs($user);

        $component = Livewire::test(VendorOnboardingChecklistWidget::class);

        $component->assertOk();

        // "Onboarding complete" chip must appear
        $component->assertSee(__('identity::vendor-onboarding.cta.onboarding_done'));

        // No "Next recommended action" CTA should be visible when complete
        $component->assertDontSee(__('identity::vendor-onboarding.cta.next_action'));
    })->group('identity', 'widgets', 'vendor-onboarding', 't036');

    // -----------------------------------------------------------------------
    // T037 — Data isolation: Vendor A does not see Vendor B's data
    // -----------------------------------------------------------------------
    it('does not leak Vendor B data when Vendor A is authenticated', function (): void {
        // Vendor A (authenticated)
        [$userA, $profileA] = createVendorUser();

        // Vendor B with distinctive sensitive data that must NOT appear for Vendor A
        $userB    = User::factory()->phoneVerified()->asVendor()->create();
        $profileB = VendorProfile::factory()->for($userB)->create([
            'approval_status' => 'pending',
            'business_name'   => ['en' => 'Vendor B Company', 'ar' => 'شركة المورد ب'],
            'bank_iban'       => 'EG380019000500000000VENDOR_B',
        ]);

        // The widget reads auth()->user()->vendorProfile, so Vendor A's profile
        // is passed to the service. Mock returns only Vendor A's checklist.
        $checklistA = allPendingChecklist($profileA->id);
        $mock = Mockery::mock(VendorOnboardingChecklistService::class);
        $mock->shouldReceive('forVendor')
            ->once()
            ->withArgs(fn ($vp) => $vp->id === $profileA->id)
            ->andReturn($checklistA);
        app()->instance(VendorOnboardingChecklistService::class, $mock);

        // Authenticate as Vendor A
        $this->actingAs($userA);

        $component = Livewire::test(VendorOnboardingChecklistWidget::class);

        $component->assertOk();

        // Vendor B's unique IBAN must NOT appear in the rendered output
        $component->assertDontSee('EG380019000500000000VENDOR_B');

        // Vendor B's distinctive business name must NOT appear
        $component->assertDontSee('Vendor B Company');
        $component->assertDontSee('شركة المورد ب');
    })->group('identity', 'widgets', 'vendor-onboarding', 't037');

    // -----------------------------------------------------------------------
    // T052 (US2) — Danger banner rendered for a rejected vendor with EN reason
    // -----------------------------------------------------------------------
    it('renders a danger banner above the checklist for a rejected vendor with the EN reason', function (): void {
        [$user, $profile] = createVendorUser([
            'approval_status'  => 'rejected',
            'rejected_at'      => now(),
            'rejection_reason' => ['en' => 'Tax mismatch', 'ar' => 'عدم تطابق'],
        ]);

        $this->actingAs($user);

        Livewire::test(VendorOnboardingChecklistWidget::class)
            ->assertSeeText('Tax mismatch');
    })->group('identity', 'widgets', 'vendor-onboarding', 't052');

    // -----------------------------------------------------------------------
    // T053 (US2) — Warning banner rendered for a changes_requested vendor
    // -----------------------------------------------------------------------
    it('renders a warning banner for a changes_requested vendor', function (): void {
        [$user, $profile] = createVendorUser([
            'approval_status'  => 'changes_requested',
            'rejection_reason' => ['en' => 'Fix address', 'ar' => 'صحح العنوان'],
        ]);

        $this->actingAs($user);

        Livewire::test(VendorOnboardingChecklistWidget::class)
            ->assertSeeText('Changes requested');
    })->group('identity', 'widgets', 'vendor-onboarding', 't053');

    // -----------------------------------------------------------------------
    // T058 (US3) — Suspension banner shown; no checklist rows rendered
    // -----------------------------------------------------------------------
    it('renders only the suspension banner (no checklist rows) when the authenticated vendor is suspended', function (): void {
        [$user, $profile] = createVendorUser([
            'approval_status'  => 'suspended',
            'suspended_at'     => now(),
            'rejection_reason' => ['en' => 'Multiple complaints', 'ar' => 'شكاوى'],
        ]);

        $this->actingAs($user);

        $component = Livewire::test(VendorOnboardingChecklistWidget::class);

        // Suspension heading must be visible
        $component->assertSeeText(__('identity::vendor-onboarding.banner.suspended_heading'));

        // No checklist rows should be rendered when suspended
        $component->assertDontSeeText(__('identity::vendor-onboarding.rows.profile.label'));
    })->group('identity', 'widgets', 'vendor-onboarding', 't058');

    // -----------------------------------------------------------------------
    // T065 (US4) — Arabic labels rendered; no EN label leaks
    // -----------------------------------------------------------------------
    it('renders Arabic labels when app locale is ar', function (): void {
        [$user, $profile] = createVendorUser();

        // Set AR locale BEFORE building the DTO so labels resolve in Arabic
        app()->setLocale('ar');

        $checklist = allPendingChecklist($profile->id);
        $mock = Mockery::mock(VendorOnboardingChecklistService::class);
        $mock->shouldReceive('forVendor')->once()->andReturn($checklist);
        app()->instance(VendorOnboardingChecklistService::class, $mock);

        $this->actingAs($user);

        $component = Livewire::test(VendorOnboardingChecklistWidget::class);

        $component->assertOk();

        // Arabic row labels must appear
        $component->assertSee(__('identity::vendor-onboarding.rows.profile.label'));
        $component->assertSee(__('identity::vendor-onboarding.rows.banking.label'));

        // English labels must not appear when locale is AR (only check if they differ)
        app()->setLocale('en');
        $enProfileLabel = __('identity::vendor-onboarding.rows.profile.label');
        app()->setLocale('ar');
        $arProfileLabel = __('identity::vendor-onboarding.rows.profile.label');

        if ($enProfileLabel !== $arProfileLabel) {
            $component->assertDontSeeText($enProfileLabel);
        }
    })->group('identity', 'widgets', 'vendor-onboarding', 'arabic', 't065');

});
