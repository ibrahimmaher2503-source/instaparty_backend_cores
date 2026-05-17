<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class CatalogDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050304);

        DB::transaction(function (): void {
            app(CatalogSeeder::class)->run();

            $categories = $this->seedCategories();
            $this->seedFieldSchemas($categories);
            $this->seedThemes();
            $services = $this->seedServices($categories);
            $this->seedInventoryReservations($services);
            $this->seedExcelImports($services);
        });

        app(CatalogImportSamplesSeeder::class)->run();
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $rows = [
            'rental-bouncy-castles' => [
                'name' => ['en' => 'Bouncy Castles', 'ar' => 'قلاع قفز'],
                'description' => ['en' => 'Inflatable rental products.', 'ar' => 'منتجات تأجير قابلة للنفخ.'],
                'types' => [ProductType::Rental],
            ],
            'rental-party-equipment' => [
                'name' => ['en' => 'Party Equipment', 'ar' => 'معدات الحفلات'],
                'description' => ['en' => 'Rental add-ons for event setups.', 'ar' => 'إضافات تأجير لتجهيز الفعاليات.'],
                'types' => [ProductType::Rental],
            ],
            'sale-cakes' => [
                'name' => ['en' => 'Cakes', 'ar' => 'كيك'],
                'description' => ['en' => 'Custom cakes and dessert tables.', 'ar' => 'كيك مخصص وطاولات حلويات.'],
                'types' => [ProductType::Sale],
            ],
            'digital-invitations' => [
                'name' => ['en' => 'Digital Invitations', 'ar' => 'دعوات رقمية'],
                'description' => ['en' => 'Editable invitation cards and templates.', 'ar' => 'دعوات ونماذج قابلة للتعديل.'],
                'types' => [ProductType::Digital],
            ],
        ];

        $categories = [];

        foreach ($rows as $code => $row) {
            /** @var Category $category */
            $category = $this->updateOrCreateFactoryModel(
                Category::factory()->active()->make([
                    'public_id' => $this->stablePublicId('category:'.$code),
                    'code' => $code,
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'allowed_product_types' => array_map(fn (ProductType $type): string => $type->value, $row['types']),
                    'sort_order' => count($categories) + 10,
                ]),
                ['code' => $code],
            );

            $categories[$code] = $category;
        }

        return $categories;
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function seedFieldSchemas(array $categories): void
    {
        $rows = [
            ['category' => 'rental-bouncy-castles', 'product_type' => ProductType::Rental, 'field_key' => 'dimensions', 'field_type' => 'text', 'label' => ['en' => 'Dimensions', 'ar' => 'الأبعاد']],
            ['category' => 'rental-bouncy-castles', 'product_type' => ProductType::Rental, 'field_key' => 'capacity', 'field_type' => 'number', 'label' => ['en' => 'Capacity', 'ar' => 'السعة']],
            ['category' => 'sale-cakes', 'product_type' => ProductType::Sale, 'field_key' => 'flavor', 'field_type' => 'select', 'label' => ['en' => 'Flavor', 'ar' => 'النكهة']],
            ['category' => 'sale-cakes', 'product_type' => ProductType::Sale, 'field_key' => 'servings', 'field_type' => 'number', 'label' => ['en' => 'Servings', 'ar' => 'عدد الحصص']],
            ['category' => 'digital-invitations', 'product_type' => ProductType::Digital, 'field_key' => 'delivery_method', 'field_type' => 'select', 'label' => ['en' => 'Delivery Method', 'ar' => 'طريقة التسليم']],
            ['category' => 'digital-invitations', 'product_type' => ProductType::Digital, 'field_key' => 'editable', 'field_type' => 'boolean', 'label' => ['en' => 'Editable', 'ar' => 'قابل للتعديل']],
        ];

        foreach ($rows as $index => $row) {
            $this->updateOrCreateFactoryModel(
                CategoryFieldSchema::factory()->make([
                    'category_id' => $categories[$row['category']]->id,
                    'product_type' => $row['product_type'],
                    'field_key' => $row['field_key'],
                    'field_label' => $row['label'],
                    'field_type' => $row['field_type'],
                    'options' => $this->fieldOptions($row['field_key']),
                    'is_required' => true,
                    'is_filterable' => $row['field_type'] !== 'text',
                    'validation_rules' => ['required'],
                    'sort_order' => $index + 1,
                ]),
                [
                    'category_id' => $categories[$row['category']]->id,
                    'product_type' => $row['product_type']->value,
                    'field_key' => $row['field_key'],
                ],
            );
        }
    }

    /**
     * @return array<int, mixed>|null
     */
    private function fieldOptions(string $fieldKey): ?array
    {
        return match ($fieldKey) {
            'flavor' => [
                ['value' => 'vanilla', 'label' => ['en' => 'Vanilla', 'ar' => 'فانيليا']],
                ['value' => 'chocolate', 'label' => ['en' => 'Chocolate', 'ar' => 'شوكولاتة']],
            ],
            'delivery_method' => [
                ['value' => 'email', 'label' => ['en' => 'Email', 'ar' => 'البريد الإلكتروني']],
                ['value' => 'whatsapp', 'label' => ['en' => 'WhatsApp', 'ar' => 'واتساب']],
            ],
            default => null,
        };
    }

    private function seedThemes(): void
    {
        foreach ([
            ['code' => 'classic-party', 'name' => ['en' => 'Classic Party', 'ar' => 'حفلة كلاسيكية']],
            ['code' => 'luxury-gold', 'name' => ['en' => 'Luxury Gold', 'ar' => 'ذهبي فاخر']],
            ['code' => 'playful-kids', 'name' => ['en' => 'Playful Kids', 'ar' => 'مرح الأطفال']],
        ] as $theme) {
            $this->updateOrCreateFactoryModel(
                ServiceTheme::factory()->active()->make([
                    'public_id' => $this->stablePublicId('service-theme:'.$theme['code']),
                    'code' => $theme['code'],
                    'name' => $theme['name'],
                ]),
                ['code' => $theme['code']],
            );
        }
    }

    /**
     * @param  array<string, Category>  $categories
     * @return array<string, Service>
     */
    private function seedServices(array $categories): array
    {
        $vendors = [
            'rental' => VendorProfile::query()->where('slug', 'joy-rentals-cairo')->firstOrFail(),
            'sale' => VendorProfile::query()->where('slug', 'sweet-table-studio')->firstOrFail(),
            'digital' => VendorProfile::query()->where('slug', 'pixel-party-cards')->firstOrFail(),
        ];

        $rows = [
            'joy-castle' => [
                'vendor' => 'rental',
                'category' => 'rental-bouncy-castles',
                'type' => ProductType::Rental,
                'name' => ['en' => 'Joy Castle 10x10', 'ar' => 'قلعة جوي 10x10'],
                'short' => ['en' => 'Inflatable rental with safety mats.', 'ar' => 'تأجير قلعة قفز مع فرش أمان.'],
                'long' => ['en' => 'Ideal for birthdays and kids events.', 'ar' => 'مثالية لأعياد الميلاد وفعاليات الأطفال.'],
                'slug' => 'joy-castle-10x10',
                'status' => ServiceStatus::Published->value,
                'price' => 250000,
                'detail' => ['duration' => 6, 'deposit' => 50000],
            ],
            'balloon-arch' => [
                'vendor' => 'rental',
                'category' => 'rental-party-equipment',
                'type' => ProductType::Rental,
                'name' => ['en' => 'Balloon Arch Setup', 'ar' => 'تجهيز قوس البالونات'],
                'short' => ['en' => 'Themed balloon arch and backdrop.', 'ar' => 'قوس بالونات وخلفية بطابع مميز.'],
                'long' => ['en' => 'A perfect photo corner for family events.', 'ar' => 'ركن تصوير مثالي للمناسبات العائلية.'],
                'slug' => 'balloon-arch-setup',
                'status' => ServiceStatus::Published->value,
                'price' => 120000,
                'detail' => ['duration' => 4, 'deposit' => 25000],
            ],
            'cake-deluxe' => [
                'vendor' => 'sale',
                'category' => 'sale-cakes',
                'type' => ProductType::Sale,
                'name' => ['en' => 'Deluxe Birthday Cake', 'ar' => 'كيك عيد ميلاد ديلوكس'],
                'short' => ['en' => 'Custom cake for 20 guests.', 'ar' => 'كيك مخصص لـ 20 ضيفا.'],
                'long' => ['en' => 'Handcrafted cake with custom toppers.', 'ar' => 'كيك مصنوع يدويا مع إضافات مخصصة.'],
                'slug' => 'deluxe-birthday-cake',
                'status' => ServiceStatus::Published->value,
                'price' => 90000,
                'detail' => ['lead_time_hours' => 24, 'stock_quantity' => 12],
            ],
            'dessert-table' => [
                'vendor' => 'sale',
                'category' => 'sale-cakes',
                'type' => ProductType::Sale,
                'name' => ['en' => 'Dessert Table Set', 'ar' => 'طقم طاولة حلويات'],
                'short' => ['en' => 'Dessert table for weddings and engagements.', 'ar' => 'طاولة حلويات للزفاف والخطوبة.'],
                'long' => ['en' => 'Includes a mixed dessert assortment and setup.', 'ar' => 'يشمل تشكيلة حلويات وتجهيز الطاولة.'],
                'slug' => 'dessert-table-set',
                'status' => ServiceStatus::PendingReview->value,
                'price' => 160000,
                'detail' => ['lead_time_hours' => 48, 'stock_quantity' => 6],
            ],
            'digital-invite' => [
                'vendor' => 'digital',
                'category' => 'digital-invitations',
                'type' => ProductType::Digital,
                'name' => ['en' => 'Animated Birthday Invite', 'ar' => 'دعوة عيد ميلاد متحركة'],
                'short' => ['en' => 'Editable birthday invitation card.', 'ar' => 'بطاقة دعوة عيد ميلاد قابلة للتعديل.'],
                'long' => ['en' => 'Delivered instantly by email or WhatsApp.', 'ar' => 'تسلم فورا عبر البريد أو واتساب.'],
                'slug' => 'animated-birthday-invite',
                'status' => ServiceStatus::Published->value,
                'price' => 45000,
                'detail' => ['delivery_method' => 'email', 'expiry_days' => 30],
            ],
            'digital-game-pack' => [
                'vendor' => 'digital',
                'category' => 'digital-invitations',
                'type' => ProductType::Digital,
                'name' => ['en' => 'Party Game Pack', 'ar' => 'حزمة ألعاب الحفلة'],
                'short' => ['en' => 'Printable games for kids and adults.', 'ar' => 'ألعاب قابلة للطباعة للأطفال والكبار.'],
                'long' => ['en' => 'A bundle of printable activities and trivia.', 'ar' => 'حزمة من الأنشطة والاختبارات المطبوعة.'],
                'slug' => 'party-game-pack',
                'status' => ServiceStatus::Draft->value,
                'price' => 30000,
                'detail' => ['delivery_method' => 'link', 'expiry_days' => null],
            ],
        ];

        $services = [];

        foreach ($rows as $key => $row) {
            /** @var Service $service */
            $service = $this->updateOrCreateFactoryModel(
                Service::factory()->make([
                    'public_id' => $this->stablePublicId('service:'.$row['slug']),
                    'vendor_profile_id' => $vendors[$row['vendor']]->id,
                    'category_id' => $categories[$row['category']]->id,
                    'product_type' => $row['type'],
                    'name' => $row['name'],
                    'short_description' => $row['short'],
                    'long_description' => $row['long'],
                    'slug' => $row['slug'],
                    'status' => $row['status'],
                    'base_price_minor' => $row['price'],
                    'base_price_currency' => 'EGP',
                    'is_featured' => in_array($key, ['joy-castle', 'digital-invite'], true),
                ]),
                ['vendor_profile_id' => $vendors[$row['vendor']]->id, 'slug' => $row['slug']],
            );

            $this->seedServiceDetail($service, $row['type'], $row['detail']);
            $services[$key] = $service;
        }

        return $services;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function seedServiceDetail(Service $service, ProductType $type, array $detail): void
    {
        match ($type) {
            ProductType::Rental => $this->updateOrCreateFactoryModel(
                ServiceRentalDetail::factory()->make([
                    'service_id' => $service->id,
                    'requires_electricity' => true,
                    'requires_outdoor_space' => true,
                    'default_rental_duration_hours' => $detail['duration'],
                    'setup_time_minutes' => 45,
                    'teardown_time_minutes' => 30,
                    'security_deposit_minor' => $detail['deposit'],
                    'security_deposit_currency' => 'EGP',
                    'minimum_space_sqm' => 20,
                ]),
                ['service_id' => $service->id],
            ),
            ProductType::Sale => $this->updateOrCreateFactoryModel(
                ServiceSaleDetail::factory()->make([
                    'service_id' => $service->id,
                    'is_perishable' => true,
                    'is_made_to_order' => true,
                    'lead_time_hours' => $detail['lead_time_hours'],
                    'stock_quantity' => $detail['stock_quantity'],
                    'customization_fields' => [
                        ['key' => 'flavor', 'type' => 'select'],
                        ['key' => 'servings', 'type' => 'number'],
                    ],
                ]),
                ['service_id' => $service->id],
            ),
            ProductType::Digital => $this->updateOrCreateFactoryModel(
                ServiceDigitalDetail::factory()->make([
                    'service_id' => $service->id,
                    'delivery_method' => $detail['delivery_method'],
                    'has_expiry' => $detail['expiry_days'] !== null,
                    'expiry_days_after_purchase' => $detail['expiry_days'],
                    'is_refundable_after_delivery' => false,
                    'redemption_url_template' => 'https://instaparty.local/redeem/{code}',
                    'code_pool_id' => null,
                ]),
                ['service_id' => $service->id],
            ),
        };
    }

    /**
     * @param  array<string, Service>  $services
     */
    private function seedInventoryReservations(array $services): void
    {
        $customers = [
            User::query()->where('email', 'customer.one@instaparty.local')->firstOrFail(),
            User::query()->where('email', 'customer.two@instaparty.local')->firstOrFail(),
        ];

        $rows = [
            ['service' => 'joy-castle', 'user' => $customers[0], 'hold' => HoldType::Payment, 'status' => ReservationStatus::Held],
            ['service' => 'cake-deluxe', 'user' => $customers[1], 'hold' => HoldType::Cart, 'status' => ReservationStatus::Confirmed],
        ];

        foreach ($rows as $index => $row) {
            $service = $services[$row['service']];

            $this->firstOrCreateFactoryModel(
                ServiceInventoryReservation::factory()->make([
                    'public_id' => $this->stablePublicId('inventory-reservation:'.$row['service'].':'.$row['user']->email),
                    'service_id' => $service->id,
                    'user_id' => $row['user']->id,
                    'product_type' => $service->product_type,
                    'hold_type' => $row['hold'],
                    'status' => $row['status'],
                    'reserved_starts_at' => $index === 0 ? '2026-05-10 10:00:00' : null,
                    'reserved_ends_at' => $index === 0 ? '2026-05-10 16:00:00' : null,
                    'quantity' => 1,
                    'expires_at' => $index === 0 ? '2026-05-03 23:59:59' : '2026-05-05 23:59:59',
                    'booking_item_id' => null,
                    'released_at' => null,
                    'release_reason' => null,
                ]),
                ['service_id' => $service->id, 'user_id' => $row['user']->id, 'hold_type' => $row['hold']->value],
            );
        }
    }

    /**
     * @param  array<string, Service>  $services
     */
    private function seedExcelImports(array $services): void
    {
        $vendor = $services['cake-deluxe']->vendor;

        /** @var ExcelImport $successfulImport */
        $successfulImport = $this->updateOrCreateFactoryModel(
            ExcelImport::factory()->completed()->make([
                'public_id' => $this->stablePublicId('excel-import:sale-services-dev.xlsx'),
                'vendor_profile_id' => $vendor->id,
                'product_type' => ProductType::Sale,
                'original_filename' => 'sale-services-dev.xlsx',
                'stored_path' => 'imports/dev/sale-services-dev.xlsx',
                'total_rows' => 2,
                'imported_rows' => 2,
                'error_rows' => 0,
            ]),
            ['vendor_profile_id' => $vendor->id, 'original_filename' => 'sale-services-dev.xlsx'],
        );

        /** @var ExcelImport $failedImport */
        $failedImport = $this->updateOrCreateFactoryModel(
            ExcelImport::factory()->failed()->make([
                'public_id' => $this->stablePublicId('excel-import:digital-services-dev.xlsx'),
                'vendor_profile_id' => $vendor->id,
                'product_type' => ProductType::Digital,
                'original_filename' => 'digital-services-dev.xlsx',
                'stored_path' => 'imports/dev/digital-services-dev.xlsx',
                'total_rows' => 2,
                'imported_rows' => 1,
                'error_rows' => 1,
            ]),
            ['vendor_profile_id' => $vendor->id, 'original_filename' => 'digital-services-dev.xlsx'],
        );

        $this->firstOrCreateFactoryModel(
            ExcelImportError::factory()->make([
                'excel_import_id' => $failedImport->id,
                'row_number' => 2,
                'field' => 'name',
                'message' => [
                    'en' => 'The name field is required.',
                    'ar' => 'حقل الاسم مطلوب.',
                ],
            ]),
            ['excel_import_id' => $failedImport->id, 'row_number' => 2, 'field' => 'name'],
        );

        unset($successfulImport);
    }
}
