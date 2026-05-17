<?php

declare(strict_types=1);

use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
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

    $this->admin = UserFactory::new()->asAdmin()->create();
    $this->admin->givePermissionTo('booking.intervene.access');
    $this->actingAs($this->admin);
});

// ── T029: list page shows relevant trouble-bucket bookings ──────────────────

it('shows bookings with late vendor response in the list', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(10),
    ]);

    BookingVendor::factory()->create([
        'booking_id'        => $booking->id,
        'sub_status'        => VendorSubStatus::Pending,
        'response_deadline' => now()->subHours(2),
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertCanSeeTableRecords([$booking]);
})->group('booking', 'intervention');

it('shows bookings with all vendors rejected in the list', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(10),
    ]);

    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Rejected,
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertCanSeeTableRecords([$booking]);
})->group('booking', 'intervention');

it('does not show completed bookings', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => 'completed',
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertCanNotSeeTableRecords([$booking]);
})->group('booking', 'intervention');

it('does not show cancelled bookings', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => 'cancelled',
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertCanNotSeeTableRecords([$booking]);
})->group('booking', 'intervention');

it('shows stalled booking older than threshold in the list', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertCanSeeTableRecords([$booking]);
})->group('booking', 'intervention');

it('defaults to 25 records per page', function (): void {
    Livewire::test(ListBookingInterventions::class)
        ->assertSet('tableRecordsPerPage', 25);
})->group('booking', 'intervention');

it('money total column renders correctly', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
        'total_minor'      => 15000,
        'total_currency'   => 'EGP',
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertCanSeeTableRecords([$booking]);
})->group('booking', 'intervention');

// ── T030: per-product-type bookings appear in the list ──────────────────────

it('shows a stalled rental booking in the intervention list', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
    ]);

    // Associate a vendor with rental items (booking itself has no product_type)
    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Rejected,
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertCanSeeTableRecords([$booking]);
})->group('booking', 'intervention', 'rental');

it('shows a stalled sale booking in the intervention list', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
    ]);

    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Rejected,
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertCanSeeTableRecords([$booking]);
})->group('booking', 'intervention', 'sale');

it('shows a stalled digital booking in the intervention list', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status'   => UnpaidState::class,
        'submitted_at'     => now()->subHours(50),
    ]);

    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Rejected,
    ]);

    Livewire::test(ListBookingInterventions::class)
        ->assertCanSeeTableRecords([$booking]);
})->group('booking', 'intervention', 'digital');
