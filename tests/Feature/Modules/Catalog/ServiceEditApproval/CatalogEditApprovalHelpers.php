<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Factories\VendorApprovedProductTypeFactory;

if (! function_exists('makeApprovedVendorWithType')) {
    function makeApprovedVendorWithType(ProductType $type): array
    {
        $user = User::factory()->phoneVerified()->asVendor()->create();
        $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
        VendorApprovedProductTypeFactory::new()->forType($type)->create([
            'vendor_profile_id' => $vendor->id,
        ]);
        $category = Category::factory()->create([
            'allowed_product_types' => [$type->value],
        ]);

        return compact('user', 'vendor', 'category');
    }
}

if (! function_exists('makePublishedServiceOfType')) {
    function makePublishedServiceOfType(ProductType $type, VendorProfile $vendor, Category $category, array $overrides = []): Service
    {
        return Service::factory()
            ->for($vendor, 'vendor')
            ->state(array_merge([
                'product_type' => $type,
                'category_id' => $category->id,
                'status' => PublishedState::$name,
                'base_price_minor' => 50000,
                'base_price_currency' => 'EGP',
                'moderated_at' => now(),
                'moderated_by' => User::factory()->asAdmin(),
            ], $overrides))
            ->create();
    }
}

if (! function_exists('openServiceChangeRequest')) {
    function openServiceChangeRequest(Service $service, VendorProfile $vendor, User $submittedBy): ServiceChangeRequest
    {
        return ServiceChangeRequest::factory()
            ->for($service, 'service')
            ->for($vendor, 'vendorProfile')
            ->state([
                'submitted_by' => $submittedBy->id,
                'product_type' => $service->product_type,
                'proposed_changes' => [
                    'shared' => ['base_price_minor' => 60000],
                    'type_specific' => [],
                    'gallery_ops' => [],
                    'availability_windows' => [],
                    'excluded_dates' => [],
                    'pricing_tiers' => [],
                ],
                'before_snapshot' => ['base_price_minor' => 50000, 'base_price_currency' => 'EGP'],
                'version' => 1,
            ])
            ->pending()
            ->create();
    }
}
