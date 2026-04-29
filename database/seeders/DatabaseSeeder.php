<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            IdentityRolesSeeder::class,
        ]);

        Artisan::call('shield:generate', ['--all' => true, '--panel' => 'admin'], $this->command->getOutput());

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::where('name', 'super_admin')->first()?->syncPermissions(Permission::all());

        $this->call([
            AdminUserSeeder::class,
            EgyptGeographySeeder::class,
        ]);
    }
}
