<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\CreateBookingModificationAction;
use App\Modules\Booking\Application\DTOs\CreateBookingModificationDTO;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../../BookingTestHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
});

it('creates a draft modification for an authorised vendor', function (): void {
    $data = makeSubmittedBookingWithVendor();

    $modification = app(CreateBookingModificationAction::class)->execute(new CreateBookingModificationDTO(
        bookingVendorId: $data['bookingVendor']->id,
        vendorProfileId: $data['vendor']->id,
        proposedByUserId: $data['vendor']->user->id,
    ));

    expect($modification->status)->toBe(ModificationStatus::Draft);
    expect($modification->booking_vendor_id)->toBe($data['bookingVendor']->id);
    expect($modification->diff_snapshot['totals']['item_count'] ?? null)->toBe(0);
})->group('booking', 'modification-builder');

it('is idempotent — re-opening returns the same draft', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $dto = new CreateBookingModificationDTO(
        bookingVendorId: $data['bookingVendor']->id,
        vendorProfileId: $data['vendor']->id,
        proposedByUserId: $data['vendor']->user->id,
    );

    $first = app(CreateBookingModificationAction::class)->execute($dto);
    $second = app(CreateBookingModificationAction::class)->execute($dto);

    expect($second->id)->toBe($first->id);
})->group('booking', 'modification-builder');

it('refuses when the caller is not the booking_vendor owner', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $otherVendor = VendorProfile::factory()->approved()->create();

    expect(fn () => app(CreateBookingModificationAction::class)->execute(new CreateBookingModificationDTO(
        bookingVendorId: $data['bookingVendor']->id,
        vendorProfileId: $otherVendor->id,
        proposedByUserId: $otherVendor->user->id,
    )))->toThrow(HttpException::class);
})->group('booking', 'modification-builder', 'auth');
