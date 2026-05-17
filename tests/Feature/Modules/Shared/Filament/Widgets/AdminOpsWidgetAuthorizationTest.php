<?php

declare(strict_types=1);

use App\Modules\Booking\Filament\Widgets\BookingsWaitingCustomerApprovalWidget;
use App\Modules\Booking\Filament\Widgets\LateVendorResponsesWidget;
use App\Modules\Catalog\Filament\Widgets\ExcelImportsWithErrorsWidget;
use App\Modules\Catalog\Filament\Widgets\PendingServiceModerationWidget;
use App\Modules\Communication\Filament\Widgets\CriticalAdminInboxWidget;
use App\Modules\Communication\Filament\Widgets\FailedNotificationDispatchesWidget;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Widgets\PendingVendorApprovalsWidget;
use App\Modules\Payments\Filament\Widgets\FailedPaymentsWidget;
use App\Modules\Settlement\Filament\Widgets\PendingWithdrawalsWidget;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

$allPermissions = [
    'view_any_vendor_profile',
    'view_any_rental_service',
    'view_any_bookings_monitor',
    'view_any_booking',
    'view_any_payment',
    'view_any_notification_dispatch',
    'view_any_withdrawals_queue',
    'view_any_excel_import',
    'view_any_admin_inbox_item',
];

beforeEach(function () use ($allPermissions): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);

    foreach ($allPermissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $superAdmin = Role::findOrCreate('super_admin', 'web');
    $superAdmin->givePermissionTo($allPermissions);
});

it('user with all permissions can view all 9 widgets', function () use ($allPermissions): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo($allPermissions);
    $this->actingAs($admin);

    expect(PendingVendorApprovalsWidget::canView())->toBeTrue();
    expect(PendingServiceModerationWidget::canView())->toBeTrue();
    expect(LateVendorResponsesWidget::canView())->toBeTrue();
    expect(BookingsWaitingCustomerApprovalWidget::canView())->toBeTrue();
    expect(FailedPaymentsWidget::canView())->toBeTrue();
    expect(FailedNotificationDispatchesWidget::canView())->toBeTrue();
    expect(PendingWithdrawalsWidget::canView())->toBeTrue();
    expect(ExcelImportsWithErrorsWidget::canView())->toBeTrue();
    expect(CriticalAdminInboxWidget::canView())->toBeTrue();
})->group('widgets', 'admin', 'authorization');

it('user with zero permissions cannot view any widget', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(PendingVendorApprovalsWidget::canView())->toBeFalse();
    expect(PendingServiceModerationWidget::canView())->toBeFalse();
    expect(LateVendorResponsesWidget::canView())->toBeFalse();
    expect(BookingsWaitingCustomerApprovalWidget::canView())->toBeFalse();
    expect(FailedPaymentsWidget::canView())->toBeFalse();
    expect(FailedNotificationDispatchesWidget::canView())->toBeFalse();
    expect(PendingWithdrawalsWidget::canView())->toBeFalse();
    expect(ExcelImportsWithErrorsWidget::canView())->toBeFalse();
    expect(CriticalAdminInboxWidget::canView())->toBeFalse();
})->group('widgets', 'admin', 'authorization');

it('user with only view_any_payment can only view FailedPaymentsWidget', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo('view_any_payment');
    $this->actingAs($admin);

    expect(PendingVendorApprovalsWidget::canView())->toBeFalse();
    expect(PendingServiceModerationWidget::canView())->toBeFalse();
    expect(LateVendorResponsesWidget::canView())->toBeFalse();
    expect(BookingsWaitingCustomerApprovalWidget::canView())->toBeFalse();
    expect(FailedPaymentsWidget::canView())->toBeTrue();
    expect(FailedNotificationDispatchesWidget::canView())->toBeFalse();
    expect(PendingWithdrawalsWidget::canView())->toBeFalse();
    expect(ExcelImportsWithErrorsWidget::canView())->toBeFalse();
    expect(CriticalAdminInboxWidget::canView())->toBeFalse();
})->group('widgets', 'admin', 'authorization');

it('super_admin role can view all 9 widgets', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    expect(PendingVendorApprovalsWidget::canView())->toBeTrue();
    expect(PendingServiceModerationWidget::canView())->toBeTrue();
    expect(LateVendorResponsesWidget::canView())->toBeTrue();
    expect(BookingsWaitingCustomerApprovalWidget::canView())->toBeTrue();
    expect(FailedPaymentsWidget::canView())->toBeTrue();
    expect(FailedNotificationDispatchesWidget::canView())->toBeTrue();
    expect(PendingWithdrawalsWidget::canView())->toBeTrue();
    expect(ExcelImportsWithErrorsWidget::canView())->toBeTrue();
    expect(CriticalAdminInboxWidget::canView())->toBeTrue();
})->group('widgets', 'admin', 'authorization');
