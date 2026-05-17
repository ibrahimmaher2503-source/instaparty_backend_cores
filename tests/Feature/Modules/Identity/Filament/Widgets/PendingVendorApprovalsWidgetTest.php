<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Widgets\PendingVendorApprovalsWidget;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    Permission::findOrCreate('view_any_vendor_profile', 'web');
});

it('returns count of pending vendors only', function (): void {
    VendorProfile::factory()->count(2)->create(['approval_status' => ApprovalStatus::Pending->value]);
    VendorProfile::factory()->create(['approval_status' => ApprovalStatus::Approved->value]);

    $widget = new PendingVendorApprovalsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(2);
})->group('widgets', 'admin', 'identity');

it('returns zero when no pending vendors exist', function (): void {
    VendorProfile::factory()->create(['approval_status' => ApprovalStatus::Approved->value]);

    $widget = new PendingVendorApprovalsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'identity');

it('canView returns true for user with view_any_vendor_profile permission', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo('view_any_vendor_profile');
    $this->actingAs($admin);

    expect(PendingVendorApprovalsWidget::canView())->toBeTrue();
})->group('widgets', 'admin', 'identity');

it('canView returns false for user without view_any_vendor_profile permission', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(PendingVendorApprovalsWidget::canView())->toBeFalse();
})->group('widgets', 'admin', 'identity');
