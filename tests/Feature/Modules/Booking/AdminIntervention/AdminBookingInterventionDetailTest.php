<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages\ViewBookingIntervention;
use Database\Factories\UserFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);

    $this->admin = UserFactory::new()->asAdmin()->create();
    $this->admin->givePermissionTo('booking.intervene.access');
    $this->actingAs($this->admin);
});

it('renders the booking detail view page without errors', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
    ]);

    Livewire::test(ViewBookingIntervention::class, ['record' => $booking->getRouteKey()])
        ->assertOk();
})->group('booking', 'intervention');

it('shows the booking reference number in the view page response', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
        'reference_no'     => 'BK-TESTREF',
    ]);

    $response = $this->get("/admin/admin-booking-intervention/{$booking->getRouteKey()}");

    $response->assertOk();
    $response->assertSee('BK-TESTREF');
})->group('booking', 'intervention');

it('does not show an edit form on the detail view page', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
    ]);

    $component = Livewire::test(ViewBookingIntervention::class, ['record' => $booking->getRouteKey()]);

    // The view page must not contain an edit form (no save button, no form submit)
    $component->assertDontSee('filament-forms');
})->group('booking', 'intervention');
