<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Shared\Domain\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'platform_name',        'value' => 'InstaParty',      'description' => 'Platform display name'],
            ['key' => 'support_email',         'value' => 'support@instaparty.eg', 'description' => 'Customer support email'],
            ['key' => 'booking_hold_minutes',  'value' => '15',              'description' => 'Cart reservation hold time (minutes)'],
            ['key' => 'payment_hold_hours',    'value' => '24',              'description' => 'Payment confirmation hold time (hours)'],
        ];

        foreach ($settings as $setting) {
            AppSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'description' => $setting['description']],
            );
        }
    }
}
