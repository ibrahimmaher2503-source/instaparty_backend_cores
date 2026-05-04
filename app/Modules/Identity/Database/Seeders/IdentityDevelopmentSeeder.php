<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\UserDevice;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class IdentityDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050302);

        DB::transaction(function (): void {
            $admin = User::query()->where('email', 'admin@instaparty.local')->firstOrFail();
            $cairo = Governorate::query()->where('code', 'EG-CAI')->firstOrFail();
            $giza = Governorate::query()->where('code', 'EG-GIZ')->firstOrFail();
            $nasrCity = City::query()->where('governorate_id', $cairo->id)->orderBy('id')->firstOrFail();
            $dokki = City::query()->where('governorate_id', $giza->id)->orderBy('id')->firstOrFail();

            $customers = $this->seedCustomers($nasrCity, $dokki);
            $vendors = $this->seedVendors($admin, $cairo, $giza, $nasrCity, $dokki);

            foreach ($vendors as $vendor) {
                $this->seedVendorCoverage($vendor, $nasrCity, $dokki);
                $this->seedBusinessHours($vendor);
            }

            foreach (array_merge($customers, array_map(fn (VendorProfile $vendor): User => $vendor->user, $vendors)) as $user) {
                $this->seedUserDevice($user);
            }
        });
    }

    /**
     * @return array<int, User>
     */
    private function seedCustomers(City $nasrCity, City $dokki): array
    {
        $rows = [
            [
                'name' => 'Nour Hassan',
                'email' => 'customer.one@instaparty.local',
                'phone' => '+201000000101',
                'locale' => 'ar',
                'city' => $nasrCity,
                'address' => '12 Makram Ebeid Street, Nasr City',
                'label' => 'Home',
            ],
            [
                'name' => 'Omar Salem',
                'email' => 'customer.two@instaparty.local',
                'phone' => '+201000000102',
                'locale' => 'en',
                'city' => $dokki,
                'address' => '45 Lebanon Square, Mohandeseen',
                'label' => 'Work',
            ],
            [
                'name' => 'Mariam Adel',
                'email' => 'customer.three@instaparty.local',
                'phone' => '+201000000103',
                'locale' => 'ar',
                'city' => $nasrCity,
                'address' => '8 South Teseen Street, New Cairo',
                'label' => 'Family',
            ],
        ];

        return array_map(function (array $row): User {
            $user = $this->seedUser(
                name: $row['name'],
                email: $row['email'],
                phone: $row['phone'],
                locale: $row['locale'],
                role: 'customer',
            );

            $this->updateOrCreateFactoryModel(
                CustomerProfile::factory()->make([
                    'user_id' => $user->id,
                    'date_of_birth' => '1992-04-18',
                    'gender' => 'prefer_not_to_say',
                    'how_heard_about_us' => 'social_media',
                    'children' => [
                        ['name' => 'Laila', 'birth_year' => 2018],
                    ],
                    'accepts_marketing' => true,
                ]),
                ['user_id' => $user->id],
            );

            $this->updateOrCreateFactoryModel(
                CustomerAddress::factory()->default()->make([
                    'public_id' => $this->stablePublicId('customer-address:'.$row['email'].':'.$row['label']),
                    'user_id' => $user->id,
                    'city_id' => $row['city']->id,
                    'label' => $row['label'],
                    'address_line' => $row['address'],
                    'building' => 'B',
                    'floor' => '3',
                    'apartment' => '12',
                    'landmark' => 'Near main gate',
                    'recipient_name' => $row['name'],
                    'recipient_phone_e164' => $row['phone'],
                ]),
                ['user_id' => $user->id, 'label' => $row['label']],
            );

            return $user;
        }, $rows);
    }

    /**
     * @return array<int, VendorProfile>
     */
    private function seedVendors(
        User $admin,
        Governorate $cairo,
        Governorate $giza,
        City $nasrCity,
        City $dokki,
    ): array {
        $rows = [
            [
                'name' => 'Salma Fouad',
                'email' => 'vendor.rental@instaparty.local',
                'phone' => '+201000000201',
                'slug' => 'joy-rentals-cairo',
                'business_name' => ['en' => 'Joy Rentals Cairo', 'ar' => 'جوي رينتالز القاهرة'],
                'bio' => ['en' => 'Premium rentals for birthdays and family events.', 'ar' => 'تأجير مميز لأعياد الميلاد والمناسبات العائلية.'],
                'business_type' => BusinessType::Company,
                'governorate' => $cairo,
                'city' => $nasrCity,
                'type' => ProductType::Rental,
                'bank_iban' => 'EG380019000500000000263180002',
            ],
            [
                'name' => 'Karim Nabil',
                'email' => 'vendor.sale@instaparty.local',
                'phone' => '+201000000202',
                'slug' => 'sweet-table-studio',
                'business_name' => ['en' => 'Sweet Table Studio', 'ar' => 'سويت تيبل ستوديو'],
                'bio' => ['en' => 'Custom cakes and dessert tables.', 'ar' => 'كيك مخصص وطاولات حلويات.'],
                'business_type' => BusinessType::Establishment,
                'governorate' => $giza,
                'city' => $dokki,
                'type' => ProductType::Sale,
                'bank_iban' => 'EG590019000500000000263180004',
            ],
            [
                'name' => 'Farida Mostafa',
                'email' => 'vendor.digital@instaparty.local',
                'phone' => '+201000000203',
                'slug' => 'pixel-party-cards',
                'business_name' => ['en' => 'Pixel Party Cards', 'ar' => 'بطاقات بيكسل بارتي'],
                'bio' => ['en' => 'Digital invitations, games, and printable party assets.', 'ar' => 'دعوات رقمية وألعاب وملفات قابلة للطباعة للحفلات.'],
                'business_type' => BusinessType::Individual,
                'governorate' => $cairo,
                'city' => $nasrCity,
                'type' => ProductType::Digital,
                'bank_iban' => 'EG820019000500000000263180006',
            ],
        ];

        return array_map(function (array $row) use ($admin): VendorProfile {
            $user = $this->seedUser(
                name: $row['name'],
                email: $row['email'],
                phone: $row['phone'],
                locale: 'ar',
                role: 'vendor',
            );

            /** @var VendorProfile $profile */
            $profile = $this->updateOrCreateFactoryModel(
                VendorProfile::factory()->approved()->make([
                    'public_id' => $this->stablePublicId('vendor-profile:'.$row['slug']),
                    'user_id' => $user->id,
                    'business_name' => $row['business_name'],
                    'slug' => $row['slug'],
                    'bio' => $row['bio'],
                    'business_type' => $row['business_type'],
                    'commercial_register_no' => 'CR-DEV-'.substr($row['phone'], -3),
                    'tax_id' => 'TAX-DEV-'.substr($row['phone'], -3),
                    'primary_governorate_id' => $row['governorate']->id,
                    'primary_city_id' => $row['city']->id,
                    'address_line' => ['en' => 'Development showroom address', 'ar' => 'عنوان معرض تجريبي'],
                    'approval_status' => ApprovalStatus::Approved,
                    'approved_at' => now(),
                    'approved_by' => $admin->id,
                    'bank_name' => 'Banque Misr',
                    'bank_account_holder' => $row['name'],
                    'bank_iban' => $row['bank_iban'],
                    'bank_swift_bic' => 'BMISEGCX',
                    'bank_branch' => 'Cairo Main',
                ]),
                ['slug' => $row['slug']],
            );

            $this->seedVendorDocuments($profile, $admin);
            $this->seedApprovedType($profile, $row['type'], $admin);

            return $profile->load('user');
        }, $rows);
    }

    private function seedUser(string $name, string $email, string $phone, string $locale, string $role): User
    {
        /** @var User $user */
        $user = $this->updateOrCreateFactoryModel(
            User::factory()->phoneVerified()->make([
                'public_id' => $this->stablePublicId('user:'.$email),
                'name' => $name,
                'email' => $email,
                'phone_e164' => $phone,
                'preferred_locale' => $locale,
                'timezone' => 'Africa/Cairo',
                'numeral_system' => 'western',
                'status' => 'active',
                'email_verified_at' => now(),
            ]),
            ['email' => $email],
        );

        $user->syncRoles([$role]);

        return $user;
    }

    private function seedVendorDocuments(VendorProfile $profile, User $admin): void
    {
        foreach ([DocumentType::Cr, DocumentType::IbanProof] as $type) {
            $this->updateOrCreateFactoryModel(
                VendorDocument::factory()->approved()->make([
                    'public_id' => $this->stablePublicId('vendor-document:'.$profile->slug.':'.$type->value),
                    'vendor_profile_id' => $profile->id,
                    'doc_type' => $type,
                    'file_path' => 'seed/vendor-documents/'.$profile->id.'/'.$type->value.'.pdf',
                    'file_name' => $type->value.'.pdf',
                    'status' => DocumentStatus::Approved,
                    'reviewed_at' => now(),
                    'reviewed_by' => $admin->id,
                    'review_notes' => ['en' => 'Approved for local development.', 'ar' => 'تمت الموافقة لبيئة التطوير المحلية.'],
                ]),
                ['vendor_profile_id' => $profile->id, 'doc_type' => $type->value],
            );
        }
    }

    private function seedApprovedType(VendorProfile $profile, ProductType $type, User $admin): void
    {
        $this->updateOrCreateFactoryModel(
            VendorApprovedProductType::factory()->forType($type)->make([
                'vendor_profile_id' => $profile->id,
                'product_type' => $type,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'revoked_at' => null,
                'revoked_by' => null,
                'revoke_reason' => null,
            ]),
            ['vendor_profile_id' => $profile->id, 'product_type' => $type->value, 'revoked_at' => null],
        );
    }

    private function seedBusinessHours(VendorProfile $profile): void
    {
        for ($day = 0; $day <= 6; $day++) {
            $isFriday = $day === 5;

            $this->updateOrCreateFactoryModel(
                VendorBusinessHour::factory()->make([
                    'vendor_profile_id' => $profile->id,
                    'day_of_week' => $day,
                    'opens_at' => $isFriday ? null : '10:00:00',
                    'closes_at' => $isFriday ? null : '22:00:00',
                ]),
                ['vendor_profile_id' => $profile->id, 'day_of_week' => $day],
            );
        }
    }

    private function seedVendorCoverage(VendorProfile $profile, City $nasrCity, City $dokki): void
    {
        foreach ([$nasrCity, $dokki] as $index => $city) {
            $this->updateOrCreateFactoryModel(
                VendorCoverageArea::factory()->make([
                    'vendor_profile_id' => $profile->id,
                    'city_id' => $city->id,
                    'delivery_fee_minor' => $index === 0 ? 5000 : 7500,
                    'delivery_fee_currency' => 'EGP',
                    'min_order_minor' => 50000,
                    'min_order_currency' => 'EGP',
                ]),
                ['vendor_profile_id' => $profile->id, 'city_id' => $city->id],
            );
        }
    }

    private function seedUserDevice(User $user): void
    {
        $this->updateOrCreateFactoryModel(
            UserDevice::factory()->android()->make([
                'user_id' => $user->id,
                'fcm_token' => 'dev-fcm-token-'.$user->id,
                'device_id' => 'dev-device-'.$user->id,
                'last_seen_at' => now(),
            ]),
            ['user_id' => $user->id, 'fcm_token' => 'dev-fcm-token-'.$user->id],
        );
    }
}
