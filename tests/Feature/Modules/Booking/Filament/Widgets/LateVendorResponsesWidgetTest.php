<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Booking\Filament\Widgets\LateVendorResponsesWidget;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
});

function makeBooking(): Booking
{
    return Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'payment_status' => UnpaidState::class,
    ]);
}

it('counts overdue vendor with past deadline and pending sub_status', function (): void {
    $booking = makeBooking();
    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'response_deadline' => now()->subHour(),
        'sub_status' => VendorSubStatus::Pending,
    ]);

    $widget = new LateVendorResponsesWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(1);
})->group('widgets', 'admin', 'booking');

it('returns zero when response_deadline is in the future', function (): void {
    $booking = makeBooking();
    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'response_deadline' => now()->addHour(),
        'sub_status' => VendorSubStatus::Pending,
    ]);

    $widget = new LateVendorResponsesWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'booking');

it('returns zero when response_deadline is NULL', function (): void {
    $booking = makeBooking();
    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'response_deadline' => null,
        'sub_status' => VendorSubStatus::Pending,
    ]);

    $widget = new LateVendorResponsesWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'booking');

it('returns zero when sub_status is accepted even if deadline is past', function (): void {
    $booking = makeBooking();
    BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'response_deadline' => now()->subHour(),
        'sub_status' => VendorSubStatus::Accepted,
    ]);

    $widget = new LateVendorResponsesWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'booking');
