<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Widgets\VendorOnboardingChecklistWidget;
use App\Modules\Shared\Filament\Vendor\Widgets\VendorRecentBookingsWidget;
use App\Modules\Shared\Filament\Vendor\Widgets\VendorStatsOverviewWidget;
use Filament\Facades\Filament;
use Livewire\Livewire;

// Feature directory already has TestCase + RefreshDatabase applied in tests/Pest.php

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

/**
 * @return array{0: User, 1: VendorProfile}
 */
function makeWidgetVendor(string $status): array
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $profile = VendorProfile::factory()->for($user)->state(['approval_status' => $status])->create();

    return [$user, $profile];
}

describe('Vendor dashboard widget visibility (BUG-010)', function (): void {

    it('shows operational widgets and hides the onboarding checklist for an approved vendor', function (): void {
        [$user] = makeWidgetVendor('approved');
        $this->actingAs($user);

        expect(VendorStatsOverviewWidget::canView())->toBeTrue();
        expect(VendorRecentBookingsWidget::canView())->toBeTrue();
        expect(VendorOnboardingChecklistWidget::canView())->toBeFalse();
    })->group('shared', 'dashboard', 'bug-010');

    it('shows the onboarding checklist and hides operational widgets for a pending vendor', function (): void {
        [$user] = makeWidgetVendor('pending');
        $this->actingAs($user);

        expect(VendorOnboardingChecklistWidget::canView())->toBeTrue();
        expect(VendorStatsOverviewWidget::canView())->toBeFalse();
        expect(VendorRecentBookingsWidget::canView())->toBeFalse();
    })->group('shared', 'dashboard', 'bug-010');

    it('renders the stats overview widget without error for an approved vendor', function (): void {
        [$user] = makeWidgetVendor('approved');
        $this->actingAs($user);

        Livewire::test(VendorStatsOverviewWidget::class)->assertOk();
    })->group('shared', 'dashboard', 'bug-010');
});

describe('Vendor recent-bookings widget (BUG-010)', function (): void {

    it('lists the vendor\'s latest bookings for an approved vendor', function (): void {
        [$user, $profile] = makeWidgetVendor('approved');

        $bookingVendor = BookingVendor::factory()->create([
            'vendor_profile_id' => $profile->id,
            'subtotal_minor' => 150_00,
        ]);

        $this->actingAs($user);

        Livewire::test(VendorRecentBookingsWidget::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$bookingVendor]);
    })->group('shared', 'dashboard', 'bug-010');

    it('caps the recent-bookings list at the 5 most recent', function (): void {
        [$user, $profile] = makeWidgetVendor('approved');

        BookingVendor::factory()->count(7)->create(['vendor_profile_id' => $profile->id]);

        $this->actingAs($user);

        Livewire::test(VendorRecentBookingsWidget::class)
            ->assertOk()
            ->assertCountTableRecords(5);
    })->group('shared', 'dashboard', 'bug-010');

    it('renders a friendly empty state when the approved vendor has no bookings', function (): void {
        [$user] = makeWidgetVendor('approved');
        $this->actingAs($user);

        Livewire::test(VendorRecentBookingsWidget::class)
            ->assertOk()
            ->assertSee(__('vendor-portal.dashboard.recent_bookings.empty_heading'));
    })->group('shared', 'dashboard', 'bug-010');
});
