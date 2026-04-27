<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            EgyptGeographySeeder::class,
        ]);
    }
}
