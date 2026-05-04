<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;

final class CatalogSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050303);

        $occasions = [
            [
                'code' => 'birthday',
                'name' => ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
                'description' => ['en' => 'Birthday celebrations', 'ar' => 'احتفالات أعياد الميلاد'],
            ],
            [
                'code' => 'wedding',
                'name' => ['en' => 'Wedding', 'ar' => 'زفاف'],
                'description' => ['en' => 'Wedding ceremonies and receptions', 'ar' => 'حفلات الزفاف والاستقبال'],
            ],
            [
                'code' => 'engagement',
                'name' => ['en' => 'Engagement', 'ar' => 'خطوبة'],
                'description' => ['en' => 'Engagement parties and celebrations', 'ar' => 'حفلات الخطوبة والاحتفالات'],
            ],
        ];

        foreach ($occasions as $index => $occasionData) {
            /** @var Occasion $occasion */
            $occasion = $this->updateOrCreateFactoryModel(
                Occasion::factory()->active()->make([
                    'public_id' => $this->stablePublicId('occasion:'.$occasionData['code']),
                    'code' => $occasionData['code'],
                    'name' => $occasionData['name'],
                    'description' => $occasionData['description'],
                    'sort_order' => $index + 1,
                ]),
                ['code' => $occasionData['code']],
            );

            /** @var Category $category */
            $category = $this->updateOrCreateFactoryModel(
                Category::factory()->active()->make([
                    'public_id' => $this->stablePublicId('category:'.$occasionData['code'].'-general'),
                    'parent_id' => null,
                    'code' => $occasionData['code'].'-general',
                    'name' => [
                        'en' => 'General '.$occasionData['name']['en'],
                        'ar' => 'عام '.$occasionData['name']['ar'],
                    ],
                    'description' => null,
                    'allowed_product_types' => array_map(
                        fn (ProductType $type): string => $type->value,
                        ProductType::cases(),
                    ),
                    'sort_order' => 0,
                ]),
                ['code' => $occasionData['code'].'-general'],
            );

            $occasion->categories()->syncWithoutDetaching([$category->id => ['sort_order' => $index + 1]]);
        }
    }
}
