<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\CapturedState;
use App\Modules\Payments\Domain\States\PaymentStatus\FailedState;
use App\Modules\Payments\Filament\Widgets\FailedPaymentsWidget;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    // Push all seeded payments outside the 48-hour window so only test-created records are counted
    DB::table('payments')->update(['created_at' => now()->subDays(3)]);
});

it('counts failed payment created within 48 hours', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => DraftState::class,
        'payment_status' => UnpaidState::class,
    ]);
    Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => FailedState::class,
        'created_at' => now()->subHour(),
    ]);

    $widget = new FailedPaymentsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(1);
})->group('widgets', 'admin', 'payments');

it('returns zero for failed payment older than 48 hours', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => DraftState::class,
        'payment_status' => UnpaidState::class,
    ]);
    Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => FailedState::class,
        'created_at' => now()->subHours(49),
    ]);

    $widget = new FailedPaymentsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'payments');

it('returns zero for captured payment within 48 hours', function (): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => DraftState::class,
        'payment_status' => UnpaidState::class,
    ]);
    Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => CapturedState::class,
        'created_at' => now()->subHour(),
    ]);

    $widget = new FailedPaymentsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'payments');

it('widget url contains status filter string', function (): void {
    $widget = new FailedPaymentsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getUrl())->toContain('status');
})->group('widgets', 'admin', 'payments');
