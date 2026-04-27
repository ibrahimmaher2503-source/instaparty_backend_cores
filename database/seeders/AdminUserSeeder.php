<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@instaparty.local'],
            [
                'public_id'   => (string) Str::ulid(),
                'name'        => 'InstaParty Admin',
                'phone_e164'  => '+20000000000',
                'password'    => bcrypt(config('app.admin_password', 'password')),
                'status'      => 'active',
            ]
        );

        $admin->assignRole('admin');
    }
}
