<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050301);

        $admin = $this->updateOrCreateFactoryModel(
            User::factory()->phoneVerified()->make([
                'public_id' => $this->stablePublicId('user:admin@instaparty.local'),
                'name' => 'InstaParty Admin',
                'email' => 'admin@instaparty.local',
                'phone_e164' => '+20000000000',
                'password' => config('app.admin_password', 'password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]),
            ['email' => 'admin@instaparty.local'],
        );

        $admin->syncRoles(['admin', 'super_admin']);
    }
}
