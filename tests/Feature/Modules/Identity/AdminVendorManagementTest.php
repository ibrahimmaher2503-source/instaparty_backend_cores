<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Application\Actions\ImpersonateVendorAction;
use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
});

it('admin can edit vendor profile without touching approval_status', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendor = VendorProfile::factory()->create([
        'approval_status' => ApprovalStatus::Approved,
        'bank_name'       => 'Old Bank',
    ]);

    $originalStatus = $vendor->approval_status;

    $this->actingAs($admin);

    app(UpdateVendorProfileAction::class)->execute($vendor, [
        'bank_name'       => 'New Bank',
        'approval_status' => ApprovalStatus::Rejected->value, // should be stripped
    ]);

    $vendor->refresh();

    expect($vendor->bank_name)->toBe('New Bank')
        ->and($vendor->approval_status)->toBe($originalStatus);
})->group('identity', 'admin-vendor-management');

it('UpdateVendorProfileAction strips all protected fields', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendor = VendorProfile::factory()->create([
        'approval_status' => ApprovalStatus::Approved,
        'slug'            => 'original-slug',
    ]);

    $this->actingAs($admin);

    app(UpdateVendorProfileAction::class)->execute($vendor, [
        'bank_name'       => 'Updated Bank',
        'slug'            => 'hacked-slug',
        'approval_status' => ApprovalStatus::Rejected->value,
        'user_id'         => 999,
    ]);

    $vendor->refresh();

    expect($vendor->slug)->toBe('original-slug')
        ->and($vendor->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($vendor->user_id)->not->toBe(999);
})->group('identity', 'admin-vendor-management');

it('impersonation creates activity log entry', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $vendor = VendorProfile::factory()->create([
        'approval_status' => ApprovalStatus::Approved,
    ]);

    $this->actingAs($admin);

    app(ImpersonateVendorAction::class)->execute($vendor, $admin);

    $logEntry = \Spatie\Activitylog\Models\Activity::where('log_name', 'default')
        ->where('description', 'vendor_impersonated')
        ->where('subject_id', $vendor->id)
        ->first();

    expect($logEntry)->not->toBeNull()
        ->and($logEntry->causer_id)->toBe($admin->id);
})->group('identity', 'admin-vendor-management');

it('impersonation creates audit_log entry', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $vendor = VendorProfile::factory()->create([
        'approval_status' => ApprovalStatus::Approved,
    ]);

    $this->actingAs($admin);

    app(ImpersonateVendorAction::class)->execute($vendor, $admin);

    $auditEntry = DB::table('audit_logs')
        ->where('auditable_type', VendorProfile::class)
        ->where('auditable_id', $vendor->id)
        ->where('action', 'vendor_impersonated')
        ->where('user_id', $admin->id)
        ->first();

    expect($auditEntry)->not->toBeNull();
})->group('identity', 'admin-vendor-management');

it('impersonation returns a valid sanctum token', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $vendor = VendorProfile::factory()->create([
        'approval_status' => ApprovalStatus::Approved,
    ]);

    $this->actingAs($admin);

    $token = app(ImpersonateVendorAction::class)->execute($vendor, $admin);

    expect($token)->toBeString()->not->toBeEmpty();
})->group('identity', 'admin-vendor-management');

it('edit does not break existing type approvals', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendor = VendorProfile::factory()->create([
        'approval_status' => ApprovalStatus::Approved,
    ]);

    $vendor->approvedProductTypes()->create([
        'product_type' => 'rental',
        'approved_at'  => now(),
        'approved_by'  => $admin->id,
    ]);

    $this->actingAs($admin);

    app(UpdateVendorProfileAction::class)->execute($vendor, ['bank_name' => 'Changed Bank']);

    expect($vendor->fresh()->approvedTypes()->count())->toBe(1);
})->group('identity', 'admin-vendor-management');
