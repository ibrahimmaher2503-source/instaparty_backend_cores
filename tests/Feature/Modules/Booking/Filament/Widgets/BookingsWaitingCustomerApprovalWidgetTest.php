<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Booking\Filament\Widgets\BookingsWaitingCustomerApprovalWidget;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
});

it('counts bookings with lifecycle_status customer_review', function (): void {
    Booking::factory()->create([
        'lifecycle_status' => CustomerReviewState::class,
        'payment_status' => UnpaidState::class,
    ]);

    $widget = new BookingsWaitingCustomerApprovalWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(1);
})->group('widgets', 'admin', 'booking');

it('returns zero when no customer_review bookings exist', function (): void {
    Booking::factory()->create([
        'lifecycle_status' => ConfirmedState::class,
        'payment_status' => UnpaidState::class,
    ]);

    $widget = new BookingsWaitingCustomerApprovalWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'booking');

it('widget url contains lifecycle_status filter string', function (): void {
    $widget = new BookingsWaitingCustomerApprovalWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getUrl())->toContain('lifecycle_status');
})->group('widgets', 'admin', 'booking');
