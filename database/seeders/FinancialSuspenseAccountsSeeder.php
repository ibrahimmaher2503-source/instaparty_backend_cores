<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialSuspenseAccountsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SuspenseAccount::cases() as $account) {
            DB::table('wallets')->insertOrIgnore([
                'public_id'                => (string) Str::ulid(),
                'owner_type'               => 'platform_account',
                'owner_id'                 => $account->value,
                'currency'                 => 'EGP',
                'balance_minor'            => 0,
                'pending_withdrawal_minor' => 0,
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
        }

        $this->command->info('Seeded ' . count(SuspenseAccount::cases()) . ' platform suspense accounts.');
    }
}
