<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class SeedE2eAdminCommand extends Command
{
    protected $signature = 'e2e:seed-admin
                            {--email=admin@instaparty.test : Admin email}
                            {--password=password : Admin password}';

    protected $description = 'Upsert the E2E super-admin user (test environments only)';

    public function handle(): int
    {
        abort_unless(
            app()->environment('local', 'testing'),
            403,
            'e2e:seed-admin may only run in local/testing environments.',
        );

        $email    = $this->option('email');
        $password = $this->option('password');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'              => 'E2E Admin',
                'email_verified_at' => now(),
                'password'          => bcrypt($password),
            ],
        );

        if (! $user->wasRecentlyCreated) {
            $user->update(['password' => bcrypt($password)]);
        }

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->syncRoles([$role]);

        $this->info("E2E admin ready: {$email}");

        return self::SUCCESS;
    }
}
