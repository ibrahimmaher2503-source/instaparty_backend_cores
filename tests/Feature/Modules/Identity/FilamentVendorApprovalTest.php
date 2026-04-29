<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
    Cache::flush();
});

it('VendorApprovalQueueResource renders pending vendors for admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    VendorProfile::factory()->count(2)->create(['approval_status' => ApprovalStatus::Pending]);
    VendorProfile::factory()->create(['approval_status' => ApprovalStatus::Approved]);

    $this->actingAs($admin);

    $query = VendorApprovalQueueResource::getEloquentQuery();
    $results = $query->get();

    expect($results)->toHaveCount(2)
        ->and($results->every(fn ($vp) => $vp->approval_status === ApprovalStatus::Pending))->toBeTrue();
})->group('identity', 'us3', 'filament');

it('VendorApprovalQueueResource canCreate returns false', function (): void {
    expect(VendorApprovalQueueResource::canCreate())->toBeFalse();
})->group('identity', 'us3', 'filament');

it('VendorProfileResource excludes non-admin users from admin actions', function (): void {
    $vendor = VendorProfile::factory()->create();
    $vendorUser = $vendor->user;

    // Vendor user should not have approve_vendor_profile permission
    expect($vendorUser->can('approve_vendor_profile'))->toBeFalse();
})->group('identity', 'us3', 'filament');
