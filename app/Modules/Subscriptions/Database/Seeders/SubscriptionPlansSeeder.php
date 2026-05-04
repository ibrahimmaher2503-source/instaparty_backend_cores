<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionPlansSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlansAndFeatures();
        $this->seedCommissionRates();
        $this->seedFeatureFlags();
        $this->seedAppSettings();
    }

    private function seedPlansAndFeatures(): void
    {
        $plans = [
            [
                'plan_code'              => 'free',
                'name'                   => json_encode(['en' => 'Free', 'ar' => 'مجاني']),
                'description'            => json_encode(['en' => 'Get started with up to 5 active services.', 'ar' => 'ابدأ مع 5 خدمات فعّالة.']),
                'monthly_price_minor'    => 0,
                'monthly_price_currency' => 'EGP',
                'yearly_price_minor'     => 0,
                'yearly_price_currency'  => 'EGP',
                'is_default'             => true,
                'is_published'           => true,
                'display_order'          => 1,
                'features'               => [
                    ['feature_key' => 'max_active_services',          'value_type' => 'int',    'value_int' => 5,     'label' => ['en' => 'Up to 5 active services',           'ar' => 'حتى 5 خدمات نشطة']],
                    ['feature_key' => 'max_gallery_images_per_service','value_type' => 'int',    'value_int' => 5,     'label' => ['en' => 'Up to 5 gallery images',             'ar' => 'حتى 5 صور معرض']],
                    ['feature_key' => 'can_feature',                   'value_type' => 'bool',   'value_bool' => false,'label' => ['en' => 'Featured listings',                 'ar' => 'القوائم المميزة']],
                    ['feature_key' => 'featured_cap',                  'value_type' => 'int',    'value_int' => 0,     'label' => ['en' => 'Featured slots',                    'ar' => 'مناصب مميزة']],
                    ['feature_key' => 'can_import_excel',              'value_type' => 'bool',   'value_bool' => false,'label' => ['en' => 'Excel bulk import',                 'ar' => 'استيراد بالجملة Excel']],
                    ['feature_key' => 'can_use_custom_branding',       'value_type' => 'bool',   'value_bool' => false,'label' => ['en' => 'Custom branding',                   'ar' => 'علامة تجارية مخصصة']],
                    ['feature_key' => 'analytics_access_level',        'value_type' => 'string', 'value_string' => 'basic', 'label' => ['en' => 'Analytics access',             'ar' => 'الوصول إلى التحليلات']],
                    ['feature_key' => 'commission_discount_bps',       'value_type' => 'int',    'value_int' => 0,     'label' => ['en' => 'Commission discount (bps)',         'ar' => 'خصم العمولة (نقطة أساس)']],
                    ['feature_key' => 'priority_support',              'value_type' => 'bool',   'value_bool' => false,'label' => ['en' => 'Priority support',                  'ar' => 'دعم أولوي']],
                ],
            ],
            [
                'plan_code'              => 'silver',
                'name'                   => json_encode(['en' => 'Silver', 'ar' => 'فضي']),
                'description'            => json_encode(['en' => 'For growing vendors with up to 25 active services.', 'ar' => 'للبائعين المتنامين مع حتى 25 خدمة نشطة.']),
                'monthly_price_minor'    => 19900,
                'monthly_price_currency' => 'EGP',
                'yearly_price_minor'     => 199000,
                'yearly_price_currency'  => 'EGP',
                'is_default'             => false,
                'is_published'           => true,
                'display_order'          => 2,
                'features'               => [
                    ['feature_key' => 'max_active_services',          'value_type' => 'int',    'value_int' => 25,    'label' => ['en' => 'Up to 25 active services',          'ar' => 'حتى 25 خدمة نشطة']],
                    ['feature_key' => 'max_gallery_images_per_service','value_type' => 'int',    'value_int' => 11,    'label' => ['en' => 'Up to 11 gallery images',            'ar' => 'حتى 11 صورة معرض']],
                    ['feature_key' => 'can_feature',                   'value_type' => 'bool',   'value_bool' => true, 'label' => ['en' => 'Featured listings',                 'ar' => 'القوائم المميزة']],
                    ['feature_key' => 'featured_cap',                  'value_type' => 'int',    'value_int' => 2,     'label' => ['en' => 'Up to 2 featured slots',            'ar' => 'حتى 2 مناصب مميزة']],
                    ['feature_key' => 'can_import_excel',              'value_type' => 'bool',   'value_bool' => false,'label' => ['en' => 'Excel bulk import',                 'ar' => 'استيراد بالجملة Excel']],
                    ['feature_key' => 'can_use_custom_branding',       'value_type' => 'bool',   'value_bool' => false,'label' => ['en' => 'Custom branding',                   'ar' => 'علامة تجارية مخصصة']],
                    ['feature_key' => 'analytics_access_level',        'value_type' => 'string', 'value_string' => 'basic', 'label' => ['en' => 'Analytics access',             'ar' => 'الوصول إلى التحليلات']],
                    ['feature_key' => 'commission_discount_bps',       'value_type' => 'int',    'value_int' => 50,    'label' => ['en' => 'Commission discount (bps)',         'ar' => 'خصم العمولة (نقطة أساس)']],
                    ['feature_key' => 'priority_support',              'value_type' => 'bool',   'value_bool' => false,'label' => ['en' => 'Priority support',                  'ar' => 'دعم أولوي']],
                ],
            ],
            [
                'plan_code'              => 'gold',
                'name'                   => json_encode(['en' => 'Gold', 'ar' => 'ذهبي']),
                'description'            => json_encode(['en' => 'For established vendors with up to 100 active services and Excel import.', 'ar' => 'للبائعين الراسخين مع حتى 100 خدمة نشطة واستيراد Excel.']),
                'monthly_price_minor'    => 49900,
                'monthly_price_currency' => 'EGP',
                'yearly_price_minor'     => 499000,
                'yearly_price_currency'  => 'EGP',
                'is_default'             => false,
                'is_published'           => true,
                'display_order'          => 3,
                'features'               => [
                    ['feature_key' => 'max_active_services',          'value_type' => 'int',    'value_int' => 100,   'label' => ['en' => 'Up to 100 active services',         'ar' => 'حتى 100 خدمة نشطة']],
                    ['feature_key' => 'max_gallery_images_per_service','value_type' => 'int',    'value_int' => 11,    'label' => ['en' => 'Up to 11 gallery images',            'ar' => 'حتى 11 صورة معرض']],
                    ['feature_key' => 'can_feature',                   'value_type' => 'bool',   'value_bool' => true, 'label' => ['en' => 'Featured listings',                 'ar' => 'القوائم المميزة']],
                    ['feature_key' => 'featured_cap',                  'value_type' => 'int',    'value_int' => 5,     'label' => ['en' => 'Up to 5 featured slots',            'ar' => 'حتى 5 مناصب مميزة']],
                    ['feature_key' => 'can_import_excel',              'value_type' => 'bool',   'value_bool' => true, 'label' => ['en' => 'Excel bulk import',                 'ar' => 'استيراد بالجملة Excel']],
                    ['feature_key' => 'can_use_custom_branding',       'value_type' => 'bool',   'value_bool' => true, 'label' => ['en' => 'Custom branding',                   'ar' => 'علامة تجارية مخصصة']],
                    ['feature_key' => 'analytics_access_level',        'value_type' => 'string', 'value_string' => 'advanced', 'label' => ['en' => 'Advanced analytics',        'ar' => 'تحليلات متقدمة']],
                    ['feature_key' => 'commission_discount_bps',       'value_type' => 'int',    'value_int' => 100,   'label' => ['en' => 'Commission discount (bps)',         'ar' => 'خصم العمولة (نقطة أساس)']],
                    ['feature_key' => 'priority_support',              'value_type' => 'bool',   'value_bool' => false,'label' => ['en' => 'Priority support',                  'ar' => 'دعم أولوي']],
                ],
            ],
            [
                'plan_code'              => 'premium',
                'name'                   => json_encode(['en' => 'Premium', 'ar' => 'مميز']),
                'description'            => json_encode(['en' => 'Unlimited services, maximum featured slots, priority support.', 'ar' => 'خدمات غير محدودة، أقصى المناصب المميزة، دعم أولوي.']),
                'monthly_price_minor'    => 99900,
                'monthly_price_currency' => 'EGP',
                'yearly_price_minor'     => 999000,
                'yearly_price_currency'  => 'EGP',
                'is_default'             => false,
                'is_published'           => true,
                'display_order'          => 4,
                'features'               => [
                    ['feature_key' => 'max_active_services',          'value_type' => 'int',    'value_int' => -1,    'label' => ['en' => 'Unlimited active services',         'ar' => 'خدمات نشطة غير محدودة']],
                    ['feature_key' => 'max_gallery_images_per_service','value_type' => 'int',    'value_int' => 11,    'label' => ['en' => 'Up to 11 gallery images',            'ar' => 'حتى 11 صورة معرض']],
                    ['feature_key' => 'can_feature',                   'value_type' => 'bool',   'value_bool' => true, 'label' => ['en' => 'Featured listings',                 'ar' => 'القوائم المميزة']],
                    ['feature_key' => 'featured_cap',                  'value_type' => 'int',    'value_int' => 15,    'label' => ['en' => 'Up to 15 featured slots',           'ar' => 'حتى 15 منصب مميز']],
                    ['feature_key' => 'can_import_excel',              'value_type' => 'bool',   'value_bool' => true, 'label' => ['en' => 'Excel bulk import',                 'ar' => 'استيراد بالجملة Excel']],
                    ['feature_key' => 'can_use_custom_branding',       'value_type' => 'bool',   'value_bool' => true, 'label' => ['en' => 'Custom branding',                   'ar' => 'علامة تجارية مخصصة']],
                    ['feature_key' => 'analytics_access_level',        'value_type' => 'string', 'value_string' => 'advanced', 'label' => ['en' => 'Advanced analytics',        'ar' => 'تحليلات متقدمة']],
                    ['feature_key' => 'commission_discount_bps',       'value_type' => 'int',    'value_int' => 200,   'label' => ['en' => 'Commission discount (bps)',         'ar' => 'خصم العمولة (نقطة أساس)']],
                    ['feature_key' => 'priority_support',              'value_type' => 'bool',   'value_bool' => true, 'label' => ['en' => 'Priority support',                  'ar' => 'دعم أولوي']],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $features = $planData['features'];
            unset($planData['features']);

            $plan = DB::table('subscription_plans')
                ->where('plan_code', $planData['plan_code'])
                ->first();

            if ($plan === null) {
                $planData['public_id'] = (string) Str::ulid();
                $planData['created_at'] = now();
                $planData['updated_at'] = now();
                $planId = DB::table('subscription_plans')->insertGetId($planData);
            } else {
                $planId = $plan->id;
            }

            foreach ($features as $feature) {
                $feature['label'] = json_encode($feature['label']);
                DB::table('plan_features')
                    ->upsert(
                        array_merge($feature, ['subscription_plan_id' => $planId, 'created_at' => now(), 'updated_at' => now()]),
                        ['subscription_plan_id', 'feature_key'],
                        ['value_type', 'value_int', 'value_bool', 'value_string', 'label', 'updated_at']
                    );
            }
        }
    }

    private function seedCommissionRates(): void
    {
        $tiers = [
            ['plan_code' => 'free',    'bps' => 1000],
            ['plan_code' => 'silver',  'bps' => 950],
            ['plan_code' => 'gold',    'bps' => 900],
            ['plan_code' => 'premium', 'bps' => 800],
        ];

        foreach ($tiers as $tier) {
            $plan = DB::table('subscription_plans')->where('plan_code', $tier['plan_code'])->first();
            if ($plan === null) {
                continue;
            }

            $exists = DB::table('commission_rates')
                ->where('subscription_plan_id', $plan->id)
                ->whereNull('category_id')
                ->whereNull('product_type')
                ->exists();

            if (! $exists) {
                DB::table('commission_rates')->insert([
                    'subscription_plan_id' => $plan->id,
                    'category_id'          => null,
                    'product_type'         => null,
                    'rate_bps'             => $tier['bps'],
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        }
    }

    private function seedFeatureFlags(): void
    {
        $exists = DB::table('feature_flags')->where('key', 'subscriptions.recurring_tokens_enabled')->exists();
        if (! $exists) {
            DB::table('feature_flags')->insert([
                'key'        => 'subscriptions.recurring_tokens_enabled',
                'value'      => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedAppSettings(): void
    {
        $exists = DB::table('app_settings')->where('key', 'subscription_grace_period_days')->exists();
        if (! $exists) {
            DB::table('app_settings')->insert([
                'key'        => 'subscription_grace_period_days',
                'value'      => '7',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
