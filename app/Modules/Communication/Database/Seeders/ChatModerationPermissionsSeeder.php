<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Idempotently seeds the six chat_moderation.* permissions
 * required by Phase 8.2 (ADR-0014) and assigns them to the `admin` role.
 */
class ChatModerationPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'chat_moderation.view',
        'chat_moderation.freeze',
        'chat_moderation.unfreeze',
        'chat_moderation.resolve_flag',
        'chat_moderation.mark_off_platform',
        'chat_moderation.escalate',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        // Assign to admin role (graceful if missing)
        try {
            $adminRole = Role::findByName('admin', 'web');
            $adminRole->givePermissionTo(self::PERMISSIONS);
        } catch (RoleDoesNotExist) {
            // admin role doesn't exist yet — skip gracefully
        }

        // Also assign to super_admin if present (created in IdentityRolesSeeder)
        try {
            $superAdmin = Role::findByName('super_admin', 'web');
            $superAdmin->givePermissionTo(self::PERMISSIONS);
        } catch (RoleDoesNotExist) {
            // super_admin role doesn't exist yet — skip gracefully
        }
    }
}
