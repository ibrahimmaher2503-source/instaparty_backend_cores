<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = $this->buildPermissions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $adminRole->syncPermissions(Permission::all());
    }

    /** @return array<string> */
    private function buildPermissions(): array
    {
        $types = ['rental', 'sale', 'digital'];

        $vendorPermissions = [];
        foreach ($types as $type) {
            $vendorPermissions[] = "service.create.{$type}.own";
            $vendorPermissions[] = "service.update.{$type}.own";
            $vendorPermissions[] = "service.delete.{$type}.own";
            $vendorPermissions[] = "service.publish.{$type}.own";
        }
        $vendorPermissions[] = 'booking.respond.own';
        $vendorPermissions[] = 'wallet.withdraw.own';

        $adminPermissions = [
            'vendor.approve',
            'vendor.approve.rental',
            'vendor.approve.sale',
            'vendor.approve.digital',
            'service.moderate',
            'commission.manage',
            'withdrawal.approve',
            'audit.view',
            'report.view',
        ];

        return array_merge($vendorPermissions, $adminPermissions);
    }
}
