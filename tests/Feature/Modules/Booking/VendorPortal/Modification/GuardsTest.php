<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\CreateBookingModificationAction;
use App\Modules\Booking\Application\Actions\PreventModificationAfterPaymentAction;
use App\Modules\Booking\Application\DTOs\CreateBookingModificationDTO;
use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Exceptions\BookingLockedException;
use App\Modules\Booking\Domain\Exceptions\BookingNotModifiableException;
use App\Modules\Booking\Domain\Exceptions\PaymentAlreadyCapturedException;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingLock;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/../../BookingTestHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    $this->data = makeSubmittedBookingWithVendor();
});

function attemptCreate(array $data): void
{
    app(CreateBookingModificationAction::class)->execute(new CreateBookingModificationDTO(
        bookingVendorId: $data['bookingVendor']->id,
        vendorProfileId: $data['vendor']->id,
        proposedByUserId: $data['vendor']->user->id,
    ));
}

it('refuses when payment is already captured', function (): void {
    DB::table('bookings')->where('id', $this->data['booking']->id)->update(['payment_status' => 'paid']);

    expect(fn () => attemptCreate($this->data))
        ->toThrow(PaymentAlreadyCapturedException::class);
})->group('booking', 'modification-builder', 'guards');

it('refuses when fulfillment is in progress', function (): void {
    $this->data['booking']->update(['fulfillment_status' => FulfillmentStatus::InProgress]);

    expect(fn () => attemptCreate($this->data))
        ->toThrow(BookingNotModifiableException::class);
})->group('booking', 'modification-builder', 'guards');

it('refuses when booking is cancelled', function (): void {
    DB::table('bookings')->where('id', $this->data['booking']->id)->update(['lifecycle_status' => 'cancelled']);

    expect(fn () => attemptCreate($this->data))
        ->toThrow(BookingNotModifiableException::class);
})->group('booking', 'modification-builder', 'guards');

it('refuses when response deadline expired', function (): void {
    $this->data['bookingVendor']->update(['response_deadline' => now()->subHour()]);

    expect(fn () => attemptCreate($this->data))
        ->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'modification-builder', 'guards');

it('refuses when an active booking lock exists', function (): void {
    BookingLock::create([
        'resource_type' => Booking::class,
        'resource_id' => $this->data['booking']->id,
        'lock_token' => 'guard-test-lock',
        'locked_by_user_id' => $this->data['customer']->id,
        'lock_purpose' => 'admin_action',
        'acquired_at' => now(),
        'expires_at' => now()->addMinutes(15),
        'released_at' => null,
    ]);

    expect(fn () => attemptCreate($this->data))
        ->toThrow(BookingLockedException::class);
})->group('booking', 'modification-builder', 'guards');

it('passes when no guard condition is triggered', function (): void {
    app(PreventModificationAfterPaymentAction::class)->execute($this->data['bookingVendor']);
    expect(true)->toBeTrue();
})->group('booking', 'modification-builder', 'guards');
