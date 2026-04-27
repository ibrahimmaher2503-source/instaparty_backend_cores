<?php

declare(strict_types=1);

namespace App\Modules\Geography\Database\Seeders;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Country;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EgyptGeographySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $egypt = $this->seedCountry();

            $this->seedGovernorate($egypt, 'EG-CAI', ['en' => 'Cairo', 'ar' => 'القاهرة'], 1, [
                ['en' => 'East Cairo',    'ar' => 'شرق القاهرة',     'cities' => [
                    ['en' => 'Nasr City',          'ar' => 'مدينة نصر'],
                    ['en' => 'Heliopolis',         'ar' => 'مصر الجديدة'],
                    ['en' => 'Ain Shams',          'ar' => 'عين شمس'],
                ]],
                ['en' => 'New Cairo',     'ar' => 'القاهرة الجديدة',  'cities' => [
                    ['en' => 'Fifth Settlement',   'ar' => 'التجمع الخامس'],
                    ['en' => 'Rehab',              'ar' => 'الرحاب'],
                    ['en' => 'Madinaty',           'ar' => 'مدينتي'],
                ]],
                ['en' => 'South Cairo',   'ar' => 'جنوب القاهرة',     'cities' => [
                    ['en' => 'Maadi',              'ar' => 'المعادي'],
                    ['en' => 'Helwan',             'ar' => 'حلوان'],
                    ['en' => 'Tura',               'ar' => 'طرة'],
                ]],
            ]);

            $this->seedGovernorate($egypt, 'EG-GIZ', ['en' => 'Giza', 'ar' => 'الجيزة'], 2, [
                ['en' => 'Greater Giza',  'ar' => 'الجيزة الكبرى',    'cities' => [
                    ['en' => 'Giza City',          'ar' => 'مدينة الجيزة'],
                    ['en' => 'Dokki',              'ar' => 'الدقي'],
                    ['en' => 'Mohandeseen',        'ar' => 'المهندسين'],
                    ['en' => 'Haram',              'ar' => 'الهرم'],
                ]],
                ['en' => '6th of October', 'ar' => 'السادس من أكتوبر',  'cities' => [
                    ['en' => '6th of October City', 'ar' => 'مدينة السادس من أكتوبر'],
                    ['en' => 'Sheikh Zayed',       'ar' => 'الشيخ زايد'],
                ]],
            ]);

            $this->seedGovernorate($egypt, 'EG-ALX', ['en' => 'Alexandria', 'ar' => 'الإسكندرية'], 3, [
                ['en' => 'East Alexandria', 'ar' => 'شرق الإسكندرية',  'cities' => [
                    ['en' => 'Montaza',            'ar' => 'المنتزه'],
                    ['en' => 'Sidi Gaber',         'ar' => 'سيدي جابر'],
                    ['en' => 'Stanley',            'ar' => 'ستانلي'],
                ]],
                ['en' => 'West Alexandria', 'ar' => 'غرب الإسكندرية',  'cities' => [
                    ['en' => 'Smouha',             'ar' => 'سموحة'],
                    ['en' => 'Agami',              'ar' => 'العجمي'],
                    ['en' => 'Borg El Arab',       'ar' => 'برج العرب'],
                ]],
            ]);

            $this->seedGovernorate($egypt, 'EG-SHR', ['en' => 'Sharqia', 'ar' => 'الشرقية'], 4, [
                ['en' => 'Zagazig Region', 'ar' => 'منطقة الزقازيق',    'cities' => [
                    ['en' => 'Zagazig',            'ar' => 'الزقازيق'],
                    ['en' => 'Belbeis',            'ar' => 'بلبيس'],
                    ['en' => 'Abu Hammad',         'ar' => 'أبو حماد'],
                ]],
                ['en' => 'Tenth of Ramadan', 'ar' => 'العاشر من رمضان', 'cities' => [
                    ['en' => '10th of Ramadan',    'ar' => 'العاشر من رمضان'],
                    ['en' => 'Minya El Qamh',      'ar' => 'منيا القمح'],
                ]],
            ]);

            $this->seedGovernorate($egypt, 'EG-QLY', ['en' => 'Qalyubia', 'ar' => 'القليوبية'], 5, [
                ['en' => 'Banha Region',  'ar' => 'منطقة بنها',        'cities' => [
                    ['en' => 'Banha',              'ar' => 'بنها'],
                    ['en' => 'Qalyub',             'ar' => 'قليوب'],
                    ['en' => 'Qaha',               'ar' => 'قها'],
                ]],
                ['en' => 'Shubra Region', 'ar' => 'منطقة شبرا',        'cities' => [
                    ['en' => 'Shoubra El Kheima',  'ar' => 'شبرا الخيمة'],
                    ['en' => 'Khanka',             'ar' => 'الخانكة'],
                ]],
            ]);
        });
    }

    private function seedCountry(): Country
    {
        return Country::firstOrCreate(
            ['iso2' => 'EG'],
            [
                'public_id' => (string) Str::ulid(),
                'name' => ['en' => 'Egypt', 'ar' => 'مصر'],
                'iso2' => 'EG',
                'iso3' => 'EGY',
                'default_currency' => 'EGP',
                'default_locale' => 'ar',
                'default_timezone' => 'Africa/Cairo',
                'phone_code' => '+20',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    /**
     * @param  array<int, array{en: string, ar: string, cities: array<int, array{en: string, ar: string}>}>  $regions
     */
    private function seedGovernorate(Country $country, string $code, array $name, int $sortOrder, array $regions): void
    {
        $gov = Governorate::firstOrCreate(
            ['code' => $code],
            [
                'public_id' => (string) Str::ulid(),
                'country_id' => $country->id,
                'name' => $name,
                'code' => $code,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );

        foreach ($regions as $regionSort => $regionData) {
            $region = Region::firstOrCreate(
                ['governorate_id' => $gov->id, 'name->en' => $regionData['en']],
                [
                    'public_id' => (string) Str::ulid(),
                    'governorate_id' => $gov->id,
                    'name' => ['en' => $regionData['en'], 'ar' => $regionData['ar']],
                    'is_active' => true,
                    'sort_order' => $regionSort + 1,
                ]
            );

            foreach ($regionData['cities'] as $citySort => $cityData) {
                City::firstOrCreate(
                    ['region_id' => $region->id, 'name->en' => $cityData['en']],
                    [
                        'public_id' => (string) Str::ulid(),
                        'region_id' => $region->id,
                        'governorate_id' => $gov->id,
                        'name' => ['en' => $cityData['en'], 'ar' => $cityData['ar']],
                        'is_active' => true,
                        'sort_order' => $citySort + 1,
                    ]
                );
            }
        }
    }
}
