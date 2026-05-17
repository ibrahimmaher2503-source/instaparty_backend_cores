<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

// Role matrix — every role returns false and writes one audit row
it('returns false and writes one audit row for every role', function (string $role): void {
    $user = User::factory()->create();
    $user->assignRole($role);
    $booking = Booking::factory()->create();

    $before = DB::table('audit_logs')
        ->where('action', 'booking.replacement_vendor_assignment_blocked')
        ->count();

    $result = Gate::forUser($user)->allows('assignReplacementVendor', $booking);

    expect($result)->toBeFalse();
    expect(
        DB::table('audit_logs')
            ->where('action', 'booking.replacement_vendor_assignment_blocked')
            ->count()
    )->toBe($before + 1);
})->with(['super_admin', 'admin', 'vendor', 'customer'])
  ->group('booking', 'policy', 'replacement-vendor-guard');

// Guest case — unauthenticated user
it('returns false and writes audit row with null user_id for unauthenticated guest', function (): void {
    $booking = Booking::factory()->create();

    $before = DB::table('audit_logs')
        ->where('action', 'booking.replacement_vendor_assignment_blocked')
        ->count();

    $result = Gate::forUser(null)->allows('assignReplacementVendor', $booking);

    expect($result)->toBeFalse();
    expect(
        DB::table('audit_logs')
            ->where('action', 'booking.replacement_vendor_assignment_blocked')
            ->whereNull('user_id')
            ->count()
    )->toBe($before + 1);
})->group('booking', 'policy', 'replacement-vendor-guard');

// Lifecycle status invariance — refusal does not depend on booking state
it('returns false regardless of lifecycle_status', function (string $state): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $booking = Booking::factory()->$state()->create();

    expect(Gate::forUser($user)->allows('assignReplacementVendor', $booking))->toBeFalse();
})->with(['draft', 'submitted', 'confirmed', 'active', 'completed', 'cancelled'])
  ->group('booking', 'policy', 'replacement-vendor-guard');

// Product type invariance — refusal does not depend on product type context
it('returns false regardless of product_type context', function (ProductType $productType): void {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $booking = Booking::factory()->create();

    // The policy ignores product_type entirely; this dataset documents that invariance
    expect(Gate::forUser($user)->allows('assignReplacementVendor', $booking))->toBeFalse();
})->with([ProductType::Rental, ProductType::Sale, ProductType::Digital])
  ->group('booking', 'policy', 'replacement-vendor-guard');

// Attempt count == audit row count
it('writes exactly one audit_logs row per Gate invocation', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $booking = Booking::factory()->create();

    $before = DB::table('audit_logs')
        ->where('action', 'booking.replacement_vendor_assignment_blocked')
        ->count();

    Gate::forUser($user)->allows('assignReplacementVendor', $booking);
    Gate::forUser($user)->allows('assignReplacementVendor', $booking);
    Gate::forUser($user)->allows('assignReplacementVendor', $booking);

    expect(
        DB::table('audit_logs')
            ->where('action', 'booking.replacement_vendor_assignment_blocked')
            ->count()
    )->toBe($before + 3);
})->group('booking', 'policy', 'replacement-vendor-guard');
