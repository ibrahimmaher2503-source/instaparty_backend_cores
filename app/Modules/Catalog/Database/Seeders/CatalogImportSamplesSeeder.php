<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Domain\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

final class CatalogImportSamplesSeeder extends Seeder
{
    public function run(): void
    {
        Storage::disk('local')->makeDirectory('seed/imports');

        $this->generateSaleSample();
        $this->generateDigitalSample();
    }

    private function generateSaleSample(): void
    {
        $categoryId = Category::query()->where('code', 'sale-cakes')->value('id') ?? 1;

        $rows = [
            [
                'name_en' => 'Deluxe Chocolate Cake',
                'name_ar' => 'كيك شوكولاتة ديلوكس',
                'short_description_en' => 'Rich chocolate cake for 20 guests',
                'short_description_ar' => 'كيك شوكولاتة غني لـ 20 ضيفا',
                'base_price_minor' => 85000,
                'category_id' => $categoryId,
                'is_perishable' => 1,
                'is_made_to_order' => 1,
                'lead_time_hours' => 24,
                'stock_quantity' => '',
            ],
            [
                'name_en' => 'Vanilla Cupcake Box',
                'name_ar' => 'صندوق كب كيك فانيليا',
                'short_description_en' => 'Box of 12 custom vanilla cupcakes',
                'short_description_ar' => 'صندوق 12 كب كيك فانيليا مخصص',
                'base_price_minor' => 45000,
                'category_id' => $categoryId,
                'is_perishable' => 1,
                'is_made_to_order' => 0,
                'lead_time_hours' => '',
                'stock_quantity' => 20,
            ],
            [
                'name_en' => 'Red Velvet Wedding Tier',
                'name_ar' => 'طابق زفاف ريد فيلفيت',
                'short_description_en' => 'Three-tier red velvet cake for weddings',
                'short_description_ar' => 'كيك ريد فيلفيت ثلاثي الطوابق للزفاف',
                'base_price_minor' => 180000,
                'category_id' => $categoryId,
                'is_perishable' => 1,
                'is_made_to_order' => 1,
                'lead_time_hours' => 48,
                'stock_quantity' => '',
            ],
        ];

        Excel::store(
            new class($rows) implements FromArray, WithHeadings
            {
                public function __construct(private readonly array $data) {}

                public function array(): array
                {
                    return $this->data;
                }

                public function headings(): array
                {
                    return [
                        'name_en', 'name_ar',
                        'short_description_en', 'short_description_ar',
                        'base_price_minor', 'category_id',
                        'is_perishable', 'is_made_to_order',
                        'lead_time_hours', 'stock_quantity',
                    ];
                }
            },
            'seed/imports/sale-services-sample.xlsx',
            'local',
        );
    }

    private function generateDigitalSample(): void
    {
        $categoryId = Category::query()->where('code', 'digital-invitations')->value('id') ?? 1;

        $rows = [
            [
                'name_en' => 'Animated Birthday Card',
                'name_ar' => 'بطاقة عيد ميلاد متحركة',
                'short_description_en' => 'Fully customisable animated birthday card',
                'short_description_ar' => 'بطاقة عيد ميلاد متحركة قابلة للتخصيص',
                'base_price_minor' => 15000,
                'category_id' => $categoryId,
                'delivery_method' => 'email',
                'has_expiry' => 0,
                'expiry_days_after_purchase' => '',
                'is_refundable_after_delivery' => 0,
                'redemption_url_template' => '',
            ],
            [
                'name_en' => 'WhatsApp Wedding Invite',
                'name_ar' => 'دعوة زفاف عبر واتساب',
                'short_description_en' => 'Premium animated wedding invitation sent via WhatsApp',
                'short_description_ar' => 'دعوة زفاف متحركة فاخرة ترسل عبر واتساب',
                'base_price_minor' => 25000,
                'category_id' => $categoryId,
                'delivery_method' => 'whatsapp',
                'has_expiry' => 1,
                'expiry_days_after_purchase' => 90,
                'is_refundable_after_delivery' => 0,
                'redemption_url_template' => '',
            ],
            [
                'name_en' => 'Printable Party Game Bundle',
                'name_ar' => 'حزمة ألعاب حفلات قابلة للطباعة',
                'short_description_en' => 'PDF party games delivered as a download link',
                'short_description_ar' => 'ألعاب حفلات PDF ترسل كرابط تحميل',
                'base_price_minor' => 10000,
                'category_id' => $categoryId,
                'delivery_method' => 'link',
                'has_expiry' => 1,
                'expiry_days_after_purchase' => 30,
                'is_refundable_after_delivery' => 1,
                'redemption_url_template' => 'https://instaparty.local/redeem/{code}',
            ],
        ];

        Excel::store(
            new class($rows) implements FromArray, WithHeadings
            {
                public function __construct(private readonly array $data) {}

                public function array(): array
                {
                    return $this->data;
                }

                public function headings(): array
                {
                    return [
                        'name_en', 'name_ar',
                        'short_description_en', 'short_description_ar',
                        'base_price_minor', 'category_id',
                        'delivery_method', 'has_expiry',
                        'expiry_days_after_purchase', 'is_refundable_after_delivery',
                        'redemption_url_template',
                    ];
                }
            },
            'seed/imports/digital-services-sample.xlsx',
            'local',
        );
    }
}
