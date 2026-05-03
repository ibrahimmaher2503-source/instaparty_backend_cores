<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SettlementPermissionsSeeder extends Seeder
{
    /**
     * Vendor-scoped permissions (assigned to the vendor role).
     */
    private const VENDOR_PERMISSIONS = [
        'settlement.view_wallet.own',
        'settlement.request_withdrawal.own',
        'settlement.view_withdrawals.own',
    ];

    /**
     * Admin-only permissions.
     */
    private const ADMIN_PERMISSIONS = [
        'settlement.approve_withdrawal',
        'settlement.reject_withdrawal',
        'settlement.view_wallet_ledger_admin',
        'settlement.manage_commission_rates',
    ];

    public function run(): void
    {
        // Create all permissions
        $allPermissions = array_merge(self::VENDOR_PERMISSIONS, self::ADMIN_PERMISSIONS);

        foreach ($allPermissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Assign vendor permissions to the vendor role (create if missing)
        $vendorRole = Role::findOrCreate('vendor', 'web');
        $vendorRole->givePermissionTo(self::VENDOR_PERMISSIONS);

        // Assign admin permissions to the admin/super_admin role
        $adminRole = Role::findOrCreate('super_admin', 'web');
        $adminRole->givePermissionTo(self::ADMIN_PERMISSIONS);

        // Also give admin role the vendor permissions so admins can view everything
        $adminRole->givePermissionTo(self::VENDOR_PERMISSIONS);

        // If a separate 'admin' role exists (non-super), assign admin perms to it too
        try {
            $regularAdminRole = Role::findByName('admin', 'web');
            $regularAdminRole->givePermissionTo(self::ADMIN_PERMISSIONS);
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist) {
            // Role doesn't exist — skip gracefully
        }
    }
}
