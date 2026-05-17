<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages\ListBookingInterventions;
use Database\Factories\UserFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);

    $this->admin = UserFactory::new()->asAdmin()->create(['preferred_locale' => 'ar']);
    $this->admin->givePermissionTo('booking.intervene.access');
    $this->actingAs($this->admin);
});

it('renders the intervention list page with AR locale without errors', function (): void {
    app()->setLocale('ar');

    Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertOk();
})->group('booking', 'intervention', 'locale');

it('renders the intervention list page with EN locale without errors', function (): void {
    app()->setLocale('en');

    Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertOk();
})->group('booking', 'intervention', 'locale');

it('HTTP response is 200 for AR locale admin on intervention page', function (): void {
    app()->setLocale('ar');

    $response = $this->get('/admin/admin-booking-intervention');

    $response->assertOk();
})->group('booking', 'intervention', 'locale');
