<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BookingPermissionsSeeder extends Seeder
{
    /**
     * Admin-only permissions for booking intervention actions (ADR-0013).
     */
    private const ADMIN_PERMISSIONS = [
        'force_cancel_booking',
        'timeout_vendor_response',
        'propose_alternative_vendor',
        'add_booking_note',
        'booking.intervene.access',
        'booking.intervene.send_vendor_reminder',
        'booking.intervene.escalate_vendor_timeout',
        'booking.intervene.suggest_alternative_vendors',
        'booking.intervene.freeze_chat',
        'booking.intervene.resume_customer_review',
        'booking.intervene.create_note',
    ];

    public function run(): void
    {
        // Create all permissions
        foreach (self::ADMIN_PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Assign to super_admin (create if missing)
        $superAdminRole = Role::findOrCreate('super_admin', 'web');
        $superAdminRole->givePermissionTo(self::ADMIN_PERMISSIONS);

        // Assign to booking_manager (create if missing)
        $bookingManagerRole = Role::findOrCreate('booking_manager', 'web');
        $bookingManagerRole->givePermissionTo(self::ADMIN_PERMISSIONS);

        // Assign to 'admin' role if it exists — skip gracefully if not found
        try {
            $adminRole = Role::findByName('admin', 'web');
            $adminRole->givePermissionTo(self::ADMIN_PERMISSIONS);
        } catch (RoleDoesNotExist) {
            // Role doesn't exist — skip gracefully
        }
    }
}
