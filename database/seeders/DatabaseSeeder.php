<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Booking\Database\Seeders\BookingDevelopmentSeeder;
use App\Modules\Booking\Database\Seeders\BookingPermissionsSeeder;
use App\Modules\Catalog\Database\Seeders\CatalogDevelopmentSeeder;
use App\Modules\Communication\Database\Seeders\CommunicationDevelopmentSeeder;
use App\Modules\Discovery\Database\Seeders\DiscoveryDevelopmentSeeder;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Database\Seeders\IdentityDevelopmentSeeder;
use App\Modules\Loyalty\Database\Seeders\LoyaltyDevelopmentSeeder;
use App\Modules\Payments\Database\Seeders\PaymentsDevelopmentSeeder;
use App\Modules\Reviews\Database\Seeders\ReviewsDevelopmentSeeder;
use App\Modules\Settlement\Database\Seeders\DefaultCommissionRatesSeeder;
use App\Modules\Settlement\Database\Seeders\SettlementDevelopmentSeeder;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
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

        Artisan::call(
            'shield:generate',
            ['--all' => true, '--panel' => 'admin', '--ignore-existing-policies' => true],
            $this->command->getOutput(),
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::where('name', 'super_admin')->first()?->syncPermissions(Permission::all());

        $this->call([
            AdminUserSeeder::class,
            EgyptGeographySeeder::class,
            IdentityDevelopmentSeeder::class,
            CatalogDevelopmentSeeder::class,
            BookingDevelopmentSeeder::class,
            BookingPermissionsSeeder::class,
            PaymentsDevelopmentSeeder::class,
            DefaultCommissionRatesSeeder::class,
            SettlementPermissionsSeeder::class,
            SettlementDevelopmentSeeder::class,
            CommunicationDevelopmentSeeder::class,
            ReviewsDevelopmentSeeder::class,
            LoyaltyDevelopmentSeeder::class,
            DiscoveryDevelopmentSeeder::class,
            CmsPagesSeeder::class,
            AppSettingsSeeder::class,
            FeatureFlagsSeeder::class,
        ]);
    }
}
