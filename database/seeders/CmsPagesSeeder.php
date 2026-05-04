<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Shared\Domain\Enums\CmsSlug;
use App\Modules\Shared\Domain\Models\CmsPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CmsPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => CmsSlug::Terms,
                'title' => ['en' => 'Terms & Conditions', 'ar' => 'الشروط والأحكام'],
                'body' => ['en' => '<p>Terms and conditions content goes here.</p>', 'ar' => '<p>محتوى الشروط والأحكام هنا.</p>'],
            ],
            [
                'slug' => CmsSlug::Privacy,
                'title' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                'body' => ['en' => '<p>Privacy policy content goes here.</p>', 'ar' => '<p>محتوى سياسة الخصوصية هنا.</p>'],
            ],
            [
                'slug' => CmsSlug::About,
                'title' => ['en' => 'About Us', 'ar' => 'من نحن'],
                'body' => ['en' => '<p>About us content goes here.</p>', 'ar' => '<p>محتوى من نحن هنا.</p>'],
            ],
            [
                'slug' => CmsSlug::Contact,
                'title' => ['en' => 'Contact Us', 'ar' => 'اتصل بنا'],
                'body' => ['en' => '<p>Contact us content goes here.</p>', 'ar' => '<p>محتوى اتصل بنا هنا.</p>'],
            ],
        ];

        foreach ($pages as $entry) {
            CmsPage::firstOrCreate(
                ['slug' => $entry['slug']->value],
                [
                    'public_id' => (string) Str::ulid(),
                    'title' => $entry['title'],
                    'body' => $entry['body'],
                    'is_published' => false,
                ],
            );
        }
    }
}
