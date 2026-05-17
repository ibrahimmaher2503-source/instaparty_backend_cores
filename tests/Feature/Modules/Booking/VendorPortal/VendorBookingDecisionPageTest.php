<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\VendorAcceptBookingAction;
use App\Modules\Booking\Application\DTOs\VendorAcceptDTO;
use App\Modules\Booking\Database\Factories\BookingAddressFactory;
use App\Modules\Booking\Database\Factories\BookingItemFactory;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingLock;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDecisionPage;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));

    // ── Vendor 1: the primary vendor under test ────────────────────────────────
    /** @var VendorProfile $vendorProfile1 */
    $vendorProfile1 = VendorProfile::factory()->approved()->create();

    /** @var User $vendorUser */
    $this->vendorUser = $vendorProfile1->user;

    // ── Vendor 2: used to assert cross-vendor 403 isolation ───────────────────
    /** @var VendorProfile $vendorProfile2 */
    $vendorProfile2 = VendorProfile::factory()->approved()->create();

    /** @var User $vendorUser2 */
    $this->vendorUser2 = $vendorProfile2->user;

    // ── Booking in vendor_review, unpaid, not yet fulfilled ───────────────────
    /** @var Booking $booking */
    $this->booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'fulfillment_status' => FulfillmentStatus::NotStarted,
        'submitted_at'     => now()->subHours(2),
    ]);

    // ── BookingAddress snapshot linked to the booking ─────────────────────────
    /** @var BookingAddress $bookingAddress */
    $this->bookingAddress = BookingAddressFactory::new()->create([
        'booking_id' => $this->booking->id,
    ]);

    // ── BookingVendor: pending, deadline 20 h from now ────────────────────────
    /** @var BookingVendor $bookingVendor */
    $this->bookingVendor = BookingVendorFactory::new()->create([
        'booking_id'        => $this->booking->id,
        'vendor_profile_id' => $vendorProfile1->id,
        'sub_status'        => VendorSubStatus::Pending,
        'response_deadline' => now()->addHours(20),
    ]);

    // ── One rental BookingItem linked to that BookingVendor ───────────────────
    /** @var BookingItem $bookingItem */
    $this->bookingItem = BookingItemFactory::new()->rental()->create([
        'booking_vendor_id' => $this->bookingVendor->id,
    ]);
});

// ── T016 — US1 — Page renders ─────────────────────────────────────────────────

it('renders the decision page for an authorised vendor', function (): void {
    Livewire::actingAs($this->vendorUser)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->assertOk()
        ->assertSee($this->booking->reference_no);
})->group('booking', 'vendor-decision', 'us1');

// ── T017 — US1 — Accept transitions sub_status ────────────────────────────────

it('accepts a pending booking and transitions sub_status to accepted', function (): void {
    Livewire::actingAs($this->vendorUser)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->callAction('accept');

    expect($this->bookingVendor->fresh()->sub_status)->toBe(VendorSubStatus::Accepted);
    expect($this->bookingVendor->fresh()->responded_at)->not->toBeNull();
})->group('booking', 'vendor-decision', 'us1');

// ── T020 — US2 — Reject with bilingual reason ─────────────────────────────────

it('rejects a pending booking with bilingual reason', function (): void {
    Livewire::actingAs($this->vendorUser)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->callAction('reject', data: ['reason_en' => 'Not available', 'reason_ar' => 'غير متاح']);

    $fresh = $this->bookingVendor->fresh();
    expect($fresh->sub_status)->toBe(VendorSubStatus::Rejected);
    expect($fresh->rejection_reason)->toBe(['en' => 'Not available', 'ar' => 'غير متاح']);
})->group('booking', 'vendor-decision', 'us2');

// ── T021 — US2 — Reject with blank reasons ────────────────────────────────────

it('allows rejection with blank reasons resulting in null rejection_reason', function (): void {
    Livewire::actingAs($this->vendorUser)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->callAction('reject', data: ['reason_en' => '', 'reason_ar' => '']);

    expect($this->bookingVendor->fresh()->rejection_reason)->toBeNull();
})->group('booking', 'vendor-decision', 'us2');

// ── T024 — US3 — Modify shows coming-soon notification when builder absent ────

it('shows a coming-soon notification when the modification builder is absent', function (): void {
    Livewire::actingAs($this->vendorUser)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->callAction('modify')
        ->assertNotified();

    // sub_status must NOT have changed
    expect($this->bookingVendor->fresh()->sub_status)->toBe(VendorSubStatus::Pending);
})->group('booking', 'vendor-decision', 'us3');

// ── T027 — US4 — Actions hidden when deadline lapsed ──────────────────────────

it('hides actions when response_deadline has lapsed', function (): void {
    $this->bookingVendor->update(['response_deadline' => now()->subHour()]);

    Livewire::actingAs($this->vendorUser)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->assertActionHidden('accept')
        ->assertActionHidden('modify')
        ->assertActionHidden('reject');
})->group('booking', 'vendor-decision', 'us4');

// ── T028 — US4 — VendorAcceptBookingAction throws 409 past deadline ───────────

it('returns ResponseDeadlineExpiredException when accept is attempted past the deadline', function (): void {
    $this->bookingVendor->update(['response_deadline' => now()->subHour()]);

    $dto = new VendorAcceptDTO(
        bookingVendorId: $this->bookingVendor->id,
        vendorProfileId: $this->vendorUser->vendorProfile->id,
        proposedByUserId: $this->vendorUser->id,
    );

    expect(fn () => app(VendorAcceptBookingAction::class)->execute($dto))
        ->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'vendor-decision', 'us4');

// ── T029 — US4 — Expired countdown banner is rendered ─────────────────────────

it('renders the Expired countdown banner when deadline is past', function (): void {
    $this->bookingVendor->update(['response_deadline' => now()->subHour()]);

    Livewire::actingAs($this->vendorUser)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->assertSee(__('vendor-portal.decision.deadline.expired'));
})->group('booking', 'vendor-decision', 'us4');

// ── T033 — US5 — Wrong vendor gets 403 ───────────────────────────────────────

it('returns 403 when a different vendor accesses the page', function (): void {
    Livewire::actingAs($this->vendorUser2)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->assertStatus(403);
})->group('booking', 'vendor-decision', 'us5');

// ── T034 — US5 — User without VendorProfile gets 403 ─────────────────────────

it('returns 403 when the user has no vendor profile', function (): void {
    $userWithoutProfile = User::factory()->create();

    Livewire::actingAs($userWithoutProfile)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->assertStatus(403);
})->group('booking', 'vendor-decision', 'us5');

// ── T036 — US5 — Actions hidden when sub_status is not pending ────────────────

it('hides actions when sub_status is no longer pending', function (): void {
    $this->bookingVendor->update(['sub_status' => VendorSubStatus::Accepted]);

    Livewire::actingAs($this->vendorUser)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->assertActionHidden('accept')
        ->assertActionHidden('modify')
        ->assertActionHidden('reject');
})->group('booking', 'vendor-decision', 'us5');

// ── T037 — US5 — Actions hidden when booking has active lock ──────────────────

it('hides actions when the booking has an active lock', function (): void {
    BookingLock::factory()->create([
        'resource_type' => 'booking',
        'resource_id'   => $this->booking->id,
        'released_at'   => null,
    ]);

    Livewire::actingAs($this->vendorUser)
        ->test(VendorBookingDecisionPage::class, ['bookingVendor' => $this->bookingVendor->public_id])
        ->assertActionHidden('accept')
        ->assertActionHidden('modify')
        ->assertActionHidden('reject');
})->group('booking', 'vendor-decision', 'us5');
