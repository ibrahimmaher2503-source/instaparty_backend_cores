<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class IdentityRolesSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->buildPermissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(
            Permission::where('name', '!=', 'impersonate_vendor')->get()
        );

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());
    }

    /** @return array<string> */
    private function buildPermissions(): array
    {
        $perTypePermissions = [];
        foreach (['rental', 'sale', 'digital'] as $type) {
            foreach (['create', 'update', 'delete', 'publish'] as $verb) {
                $perTypePermissions[] = "service.{$verb}.{$type}.own";
            }
        }

        $serviceModerationPermissions = [
            'publish_rental_service',
            'publish_sale_service',
            'publish_digital_service',
            'reject_service',
            'request_service_edits',
            'archive_service',
            'moderate_service',
            // Phase 8.0.1: staged-edit approval gates (one per product type)
            'service.moderate.rental',
            'service.moderate.sale',
            'service.moderate.digital',
        ];

        $adminPermissions = [
            'approve_vendor_profile',
            'reject_vendor_profile',
            'suspend_vendor',
            'approve_vendor_for_type',
            'approve_vendor_for_rental',
            'approve_vendor_for_sale',
            'approve_vendor_for_digital',
            'revoke_vendor_type',
            'review_vendor_documents',
            'manage_vendor_profile',
            'impersonate_vendor',
            're_upload_vendor_document',
        ];

        $vendorOwnPermissions = [
            'booking.respond.own',
            'wallet.withdraw.own',
        ];

        $bookingMonitorPermissions = [
            'view_any_bookings::monitor',
            'view_bookings::monitor',
            'update_bookings::monitor',
        ];

        $paymentViewPermissions = [
            'view_any_payment',
            'view_payment',
        ];

        $interventionPermissions = [
            'booking.intervene.access',
            'force_cancel_booking',
        ];

        return array_merge(
            $perTypePermissions,
            $serviceModerationPermissions,
            $adminPermissions,
            $vendorOwnPermissions,
            $bookingMonitorPermissions,
            $paymentViewPermissions,
            $interventionPermissions,
        );
    }
}
