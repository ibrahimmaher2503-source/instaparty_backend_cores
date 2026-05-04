<?php

declare(strict_types=1);

namespace App\Modules\Geography\Database\Seeders;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Country;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EgyptGeographySeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050301);

        DB::transaction(function (): void {
            $egypt = $this->seedCountry();

            foreach ($this->governorates() as $index => $governorate) {
                $this->seedGovernorate(
                    $egypt,
                    $governorate['code'],
                    $governorate['name'],
                    $index + 1,
                    $governorate['regions'],
                );
            }
        });
    }

    private function seedCountry(): Country
    {
        /** @var Country $country */
        $country = $this->updateOrCreateFactoryModel(
            Country::factory()->make([
                'public_id' => $this->stablePublicId('country:EG'),
                'name' => ['en' => 'Egypt', 'ar' => 'مصر'],
                'iso2' => 'EG',
                'iso3' => 'EGY',
                'default_currency' => 'EGP',
                'default_locale' => 'ar',
                'default_timezone' => 'Africa/Cairo',
                'phone_code' => '+20',
                'is_active' => true,
                'sort_order' => 1,
            ]),
            ['iso2' => 'EG'],
        );

        return $country;
    }

    /**
     * @param  array{en: string, ar: string}  $name
     * @param  array<int, array{en: string, ar: string, cities: array<int, array{en: string, ar: string}>}>  $regions
     */
    private function seedGovernorate(Country $country, string $code, array $name, int $sortOrder, array $regions): void
    {
        /** @var Governorate $governorate */
        $governorate = $this->updateOrCreateFactoryModel(
            Governorate::factory()->make([
                'public_id' => $this->stablePublicId('governorate:'.$code),
                'country_id' => $country->id,
                'name' => $name,
                'code' => $code,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]),
            ['code' => $code],
        );

        foreach ($regions as $regionSort => $regionData) {
            $existingRegion = Region::query()
                ->where('governorate_id', $governorate->id)
                ->where('name->en', $regionData['en'])
                ->first();
            $stableRegionPublicId = $this->stablePublicId('region:'.$code.':'.$regionData['en']);

            if ($existingRegion !== null && ! Str::isUlid((string) $existingRegion->public_id)) {
                $existingRegion->forceFill(['public_id' => $stableRegionPublicId])->save();
                $existingRegion->refresh();
            }

            $regionPublicId = $existingRegion !== null
                ? $existingRegion->public_id
                : $stableRegionPublicId;

            /** @var Region $region */
            $region = $this->updateOrCreateFactoryModel(
                Region::factory()->make([
                    'public_id' => $regionPublicId,
                    'governorate_id' => $governorate->id,
                    'name' => ['en' => $regionData['en'], 'ar' => $regionData['ar']],
                    'is_active' => true,
                    'sort_order' => $regionSort + 1,
                ]),
                ['public_id' => $regionPublicId],
            );

            foreach ($regionData['cities'] as $citySort => $cityData) {
                $existingCity = City::query()
                    ->where('region_id', $region->id)
                    ->where('name->en', $cityData['en'])
                    ->first();
                $stableCityPublicId = $this->stablePublicId('city:'.$code.':'.$regionData['en'].':'.$cityData['en']);

                if ($existingCity !== null && ! Str::isUlid((string) $existingCity->public_id)) {
                    $existingCity->forceFill(['public_id' => $stableCityPublicId])->save();
                    $existingCity->refresh();
                }

                $cityPublicId = $existingCity !== null
                    ? $existingCity->public_id
                    : $stableCityPublicId;

                $this->updateOrCreateFactoryModel(
                    City::factory()->make([
                        'public_id' => $cityPublicId,
                        'region_id' => $region->id,
                        'governorate_id' => $governorate->id,
                        'name' => ['en' => $cityData['en'], 'ar' => $cityData['ar']],
                        'latitude' => null,
                        'longitude' => null,
                        'is_active' => true,
                        'sort_order' => $citySort + 1,
                    ]),
                    ['public_id' => $cityPublicId],
                );
            }
        }
    }

    /**
     * @return array<int, array{
     *     code: string,
     *     name: array{en: string, ar: string},
     *     regions: array<int, array{en: string, ar: string, cities: array<int, array{en: string, ar: string}>}>
     * }>
     */
    private function governorates(): array
    {
        return [
            [
                'code' => 'EG-CAI',
                'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
                'regions' => [
                    ['en' => 'East Cairo', 'ar' => 'شرق القاهرة', 'cities' => [
                        ['en' => 'Nasr City', 'ar' => 'مدينة نصر'],
                        ['en' => 'Heliopolis', 'ar' => 'مصر الجديدة'],
                        ['en' => 'Ain Shams', 'ar' => 'عين شمس'],
                    ]],
                    ['en' => 'New Cairo', 'ar' => 'القاهرة الجديدة', 'cities' => [
                        ['en' => 'Fifth Settlement', 'ar' => 'التجمع الخامس'],
                        ['en' => 'Rehab', 'ar' => 'الرحاب'],
                        ['en' => 'Madinaty', 'ar' => 'مدينتي'],
                    ]],
                    ['en' => 'South Cairo', 'ar' => 'جنوب القاهرة', 'cities' => [
                        ['en' => 'Maadi', 'ar' => 'المعادي'],
                        ['en' => 'Helwan', 'ar' => 'حلوان'],
                        ['en' => 'Tura', 'ar' => 'طرة'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-GIZ',
                'name' => ['en' => 'Giza', 'ar' => 'الجيزة'],
                'regions' => [
                    ['en' => 'Greater Giza', 'ar' => 'الجيزة الكبرى', 'cities' => [
                        ['en' => 'Giza City', 'ar' => 'مدينة الجيزة'],
                        ['en' => 'Dokki', 'ar' => 'الدقي'],
                        ['en' => 'Mohandeseen', 'ar' => 'المهندسين'],
                        ['en' => 'Haram', 'ar' => 'الهرم'],
                    ]],
                    ['en' => '6th of October', 'ar' => 'السادس من أكتوبر', 'cities' => [
                        ['en' => '6th of October City', 'ar' => 'مدينة السادس من أكتوبر'],
                        ['en' => 'Sheikh Zayed', 'ar' => 'الشيخ زايد'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-ALX',
                'name' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية'],
                'regions' => [
                    ['en' => 'East Alexandria', 'ar' => 'شرق الإسكندرية', 'cities' => [
                        ['en' => 'Montaza', 'ar' => 'المنتزه'],
                        ['en' => 'Sidi Gaber', 'ar' => 'سيدي جابر'],
                        ['en' => 'Stanley', 'ar' => 'ستانلي'],
                    ]],
                    ['en' => 'West Alexandria', 'ar' => 'غرب الإسكندرية', 'cities' => [
                        ['en' => 'Smouha', 'ar' => 'سموحة'],
                        ['en' => 'Agami', 'ar' => 'العجمي'],
                        ['en' => 'Borg El Arab', 'ar' => 'برج العرب'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-SHR',
                'name' => ['en' => 'Sharqia', 'ar' => 'الشرقية'],
                'regions' => [
                    ['en' => 'Zagazig Region', 'ar' => 'منطقة الزقازيق', 'cities' => [
                        ['en' => 'Zagazig', 'ar' => 'الزقازيق'],
                        ['en' => 'Belbeis', 'ar' => 'بلبيس'],
                        ['en' => 'Abu Hammad', 'ar' => 'أبو حماد'],
                    ]],
                    ['en' => 'Tenth of Ramadan', 'ar' => 'العاشر من رمضان', 'cities' => [
                        ['en' => '10th of Ramadan', 'ar' => 'العاشر من رمضان'],
                        ['en' => 'Minya El Qamh', 'ar' => 'منيا القمح'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-QLY',
                'name' => ['en' => 'Qalyubia', 'ar' => 'القليوبية'],
                'regions' => [
                    ['en' => 'Banha Region', 'ar' => 'منطقة بنها', 'cities' => [
                        ['en' => 'Banha', 'ar' => 'بنها'],
                        ['en' => 'Qalyub', 'ar' => 'قليوب'],
                        ['en' => 'Qaha', 'ar' => 'قها'],
                    ]],
                    ['en' => 'Shubra Region', 'ar' => 'منطقة شبرا', 'cities' => [
                        ['en' => 'Shoubra El Kheima', 'ar' => 'شبرا الخيمة'],
                        ['en' => 'Khanka', 'ar' => 'الخانكة'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-DKH',
                'name' => ['en' => 'Dakahlia', 'ar' => 'الدقهلية'],
                'regions' => [
                    ['en' => 'Mansoura Region', 'ar' => 'منطقة المنصورة', 'cities' => [
                        ['en' => 'Mansoura', 'ar' => 'المنصورة'],
                        ['en' => 'Talkha', 'ar' => 'طلخا'],
                        ['en' => 'Mit Ghamr', 'ar' => 'ميت غمر'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-GHB',
                'name' => ['en' => 'Gharbia', 'ar' => 'الغربية'],
                'regions' => [
                    ['en' => 'Tanta Region', 'ar' => 'منطقة طنطا', 'cities' => [
                        ['en' => 'Tanta', 'ar' => 'طنطا'],
                        ['en' => 'El Mahalla El Kubra', 'ar' => 'المحلة الكبرى'],
                        ['en' => 'Kafr El Zayat', 'ar' => 'كفر الزيات'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-MNF',
                'name' => ['en' => 'Monufia', 'ar' => 'المنوفية'],
                'regions' => [
                    ['en' => 'Shibin El Kom Region', 'ar' => 'منطقة شبين الكوم', 'cities' => [
                        ['en' => 'Shibin El Kom', 'ar' => 'شبين الكوم'],
                        ['en' => 'Menouf', 'ar' => 'منوف'],
                        ['en' => 'Ashmoun', 'ar' => 'أشمون'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-KFS',
                'name' => ['en' => 'Kafr El Sheikh', 'ar' => 'كفر الشيخ'],
                'regions' => [
                    ['en' => 'Kafr El Sheikh Region', 'ar' => 'منطقة كفر الشيخ', 'cities' => [
                        ['en' => 'Kafr El Sheikh', 'ar' => 'كفر الشيخ'],
                        ['en' => 'Desouk', 'ar' => 'دسوق'],
                        ['en' => 'Baltim', 'ar' => 'بلطيم'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-BHR',
                'name' => ['en' => 'Beheira', 'ar' => 'البحيرة'],
                'regions' => [
                    ['en' => 'Damanhur Region', 'ar' => 'منطقة دمنهور', 'cities' => [
                        ['en' => 'Damanhur', 'ar' => 'دمنهور'],
                        ['en' => 'Kafr El Dawwar', 'ar' => 'كفر الدوار'],
                        ['en' => 'Rashid', 'ar' => 'رشيد'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-DMT',
                'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
                'regions' => [
                    ['en' => 'Damietta Region', 'ar' => 'منطقة دمياط', 'cities' => [
                        ['en' => 'Damietta', 'ar' => 'دمياط'],
                        ['en' => 'New Damietta', 'ar' => 'دمياط الجديدة'],
                        ['en' => 'Ras El Bar', 'ar' => 'رأس البر'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-PTS',
                'name' => ['en' => 'Port Said', 'ar' => 'بورسعيد'],
                'regions' => [
                    ['en' => 'Port Said Region', 'ar' => 'منطقة بورسعيد', 'cities' => [
                        ['en' => 'Port Said', 'ar' => 'بورسعيد'],
                        ['en' => 'Port Fouad', 'ar' => 'بورفؤاد'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-ISM',
                'name' => ['en' => 'Ismailia', 'ar' => 'الإسماعيلية'],
                'regions' => [
                    ['en' => 'Ismailia Region', 'ar' => 'منطقة الإسماعيلية', 'cities' => [
                        ['en' => 'Ismailia', 'ar' => 'الإسماعيلية'],
                        ['en' => 'Fayed', 'ar' => 'فايد'],
                        ['en' => 'Qantara', 'ar' => 'القنطرة'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-SUZ',
                'name' => ['en' => 'Suez', 'ar' => 'السويس'],
                'regions' => [
                    ['en' => 'Suez Region', 'ar' => 'منطقة السويس', 'cities' => [
                        ['en' => 'Suez', 'ar' => 'السويس'],
                        ['en' => 'Ain Sokhna', 'ar' => 'العين السخنة'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-NSI',
                'name' => ['en' => 'North Sinai', 'ar' => 'شمال سيناء'],
                'regions' => [
                    ['en' => 'Arish Region', 'ar' => 'منطقة العريش', 'cities' => [
                        ['en' => 'Arish', 'ar' => 'العريش'],
                        ['en' => 'Sheikh Zuweid', 'ar' => 'الشيخ زويد'],
                        ['en' => 'Bir El Abd', 'ar' => 'بئر العبد'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-SSI',
                'name' => ['en' => 'South Sinai', 'ar' => 'جنوب سيناء'],
                'regions' => [
                    ['en' => 'Sharm El Sheikh Region', 'ar' => 'منطقة شرم الشيخ', 'cities' => [
                        ['en' => 'Sharm El Sheikh', 'ar' => 'شرم الشيخ'],
                        ['en' => 'Dahab', 'ar' => 'دهب'],
                        ['en' => 'Nuweiba', 'ar' => 'نويبع'],
                        ['en' => 'El Tor', 'ar' => 'الطور'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-RS',
                'name' => ['en' => 'Red Sea', 'ar' => 'البحر الأحمر'],
                'regions' => [
                    ['en' => 'Hurghada Region', 'ar' => 'منطقة الغردقة', 'cities' => [
                        ['en' => 'Hurghada', 'ar' => 'الغردقة'],
                        ['en' => 'Safaga', 'ar' => 'سفاجا'],
                        ['en' => 'Marsa Alam', 'ar' => 'مرسى علم'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-FYM',
                'name' => ['en' => 'Fayoum', 'ar' => 'الفيوم'],
                'regions' => [
                    ['en' => 'Fayoum Region', 'ar' => 'منطقة الفيوم', 'cities' => [
                        ['en' => 'Fayoum', 'ar' => 'الفيوم'],
                        ['en' => 'Senuris', 'ar' => 'سنورس'],
                        ['en' => 'Tamiya', 'ar' => 'طامية'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-BNS',
                'name' => ['en' => 'Beni Suef', 'ar' => 'بني سويف'],
                'regions' => [
                    ['en' => 'Beni Suef Region', 'ar' => 'منطقة بني سويف', 'cities' => [
                        ['en' => 'Beni Suef', 'ar' => 'بني سويف'],
                        ['en' => 'Beba', 'ar' => 'ببا'],
                        ['en' => 'Nasser', 'ar' => 'ناصر'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-MIN',
                'name' => ['en' => 'Minya', 'ar' => 'المنيا'],
                'regions' => [
                    ['en' => 'Minya Region', 'ar' => 'منطقة المنيا', 'cities' => [
                        ['en' => 'Minya', 'ar' => 'المنيا'],
                        ['en' => 'Mallawi', 'ar' => 'ملوي'],
                        ['en' => 'Samalut', 'ar' => 'سمالوط'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-AST',
                'name' => ['en' => 'Asyut', 'ar' => 'أسيوط'],
                'regions' => [
                    ['en' => 'Asyut Region', 'ar' => 'منطقة أسيوط', 'cities' => [
                        ['en' => 'Asyut', 'ar' => 'أسيوط'],
                        ['en' => 'Abnub', 'ar' => 'أبنوب'],
                        ['en' => 'Dairut', 'ar' => 'ديروط'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-SHG',
                'name' => ['en' => 'Sohag', 'ar' => 'سوهاج'],
                'regions' => [
                    ['en' => 'Sohag Region', 'ar' => 'منطقة سوهاج', 'cities' => [
                        ['en' => 'Sohag', 'ar' => 'سوهاج'],
                        ['en' => 'Akhmim', 'ar' => 'أخميم'],
                        ['en' => 'Tahta', 'ar' => 'طهطا'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-QNA',
                'name' => ['en' => 'Qena', 'ar' => 'قنا'],
                'regions' => [
                    ['en' => 'Qena Region', 'ar' => 'منطقة قنا', 'cities' => [
                        ['en' => 'Qena', 'ar' => 'قنا'],
                        ['en' => 'Nag Hammadi', 'ar' => 'نجع حمادي'],
                        ['en' => 'Qus', 'ar' => 'قوص'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-LXR',
                'name' => ['en' => 'Luxor', 'ar' => 'الأقصر'],
                'regions' => [
                    ['en' => 'Luxor Region', 'ar' => 'منطقة الأقصر', 'cities' => [
                        ['en' => 'Luxor', 'ar' => 'الأقصر'],
                        ['en' => 'Esna', 'ar' => 'إسنا'],
                        ['en' => 'Armant', 'ar' => 'أرمنت'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-ASN',
                'name' => ['en' => 'Aswan', 'ar' => 'أسوان'],
                'regions' => [
                    ['en' => 'Aswan Region', 'ar' => 'منطقة أسوان', 'cities' => [
                        ['en' => 'Aswan', 'ar' => 'أسوان'],
                        ['en' => 'Kom Ombo', 'ar' => 'كوم أمبو'],
                        ['en' => 'Edfu', 'ar' => 'إدفو'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-WAD',
                'name' => ['en' => 'New Valley', 'ar' => 'الوادي الجديد'],
                'regions' => [
                    ['en' => 'Kharga Region', 'ar' => 'منطقة الخارجة', 'cities' => [
                        ['en' => 'Kharga', 'ar' => 'الخارجة'],
                        ['en' => 'Dakhla', 'ar' => 'الداخلة'],
                        ['en' => 'Farafra', 'ar' => 'الفرافرة'],
                    ]],
                ],
            ],
            [
                'code' => 'EG-MTR',
                'name' => ['en' => 'Matrouh', 'ar' => 'مطروح'],
                'regions' => [
                    ['en' => 'Marsa Matrouh Region', 'ar' => 'منطقة مرسى مطروح', 'cities' => [
                        ['en' => 'Marsa Matrouh', 'ar' => 'مرسى مطروح'],
                        ['en' => 'Siwa', 'ar' => 'سيوة'],
                        ['en' => 'El Alamein', 'ar' => 'العلمين'],
                    ]],
                ],
            ],
        ];
    }
}
