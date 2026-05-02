<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $occasions = [
            [
                'code' => 'birthday',
                'name' => ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
                'description' => ['en' => 'Birthday celebrations', 'ar' => 'الاحتفالات بأعياد الميلاد'],
            ],
            [
                'code' => 'wedding',
                'name' => ['en' => 'Wedding', 'ar' => 'زفاف'],
                'description' => ['en' => 'Wedding ceremonies and receptions', 'ar' => 'حفلات الزفاف والأعراس'],
            ],
            [
                'code' => 'engagement',
                'name' => ['en' => 'Engagement', 'ar' => 'خطوبة'],
                'description' => ['en' => 'Engagement parties and celebrations', 'ar' => 'حفلات الخطوبة والاحتفالات'],
            ],
        ];

        foreach ($occasions as $index => $occasionData) {
            $occasion = Occasion::firstOrCreate(
                ['code' => $occasionData['code']],
                [
                    'public_id' => (string) Str::ulid(),
                    'name' => $occasionData['name'],
                    'description' => $occasionData['description'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );

            $category = Category::firstOrCreate(
                ['code' => $occasionData['code'].'-general'],
                [
                    'public_id' => (string) Str::ulid(),
                    'parent_id' => null,
                    'name' => [
                        'en' => 'General '.$occasionData['name']['en'],
                        'ar' => 'عام '.$occasionData['name']['ar'],
                    ],
                    'description' => null,
                    'allowed_product_types' => ['rental', 'sale', 'digital'],
                    'sort_order' => 0,
                    'is_active' => true,
                ]
            );

            $occasion->categories()->syncWithoutDetaching([$category->id]);
        }
    }
}
