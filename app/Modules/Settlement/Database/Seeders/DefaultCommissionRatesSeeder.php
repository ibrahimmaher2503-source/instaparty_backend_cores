<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DefaultCommissionRatesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('commission_rates')->insertOrIgnore([
            'public_id' => (string) Str::ulid(),
            'category_id' => null,
            'product_type' => null,
            'commission_bps' => 1500, // 15% platform default
            'effective_from' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
