<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\CreateDigitalServiceAction;
use App\Modules\Catalog\Application\Actions\CreateRentalServiceAction;
use App\Modules\Catalog\Application\Actions\CreateSaleServiceAction;
use App\Modules\Catalog\Application\DTOs\CreateDigitalServiceDTO;
use App\Modules\Catalog\Application\DTOs\CreateRentalServiceDTO;
use App\Modules\Catalog\Application\DTOs\CreateSaleServiceDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Application\Actions\AutoEnrolFreeTierAction;
use App\Modules\Subscriptions\Database\Seeders\SubscriptionPlansSeeder;
use App\Modules\Subscriptions\Domain\Exceptions\SubscriptionLimitReachedException;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
    $this->seed(SubscriptionPlansSeeder::class);
});

function makeFreeVendor(): VendorProfile
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    app(AutoEnrolFreeTierAction::class)->execute($vendor->id);

    return $vendor;
}

function fillServices(VendorProfile $vendor, ProductType $type, int $count): void
{
    $category = Category::factory()->create(['allowed_product_types' => [$type->value]]);

    for ($i = 0; $i < $count; $i++) {
        Service::create([
            'public_id' => Str::ulid()->toBase32(),
            'vendor_profile_id' => $vendor->id,
            'category_id' => $category->id,
            'product_type' => $type,
            'name' => ['en' => "Svc $i", 'ar' => "خدمة $i"],
            'short_description' => ['en' => 'd', 'ar' => 'd'],
            'slug' => 'svc-'.$i.'-'.Str::lower(Str::random(4)),
            'status' => ServiceStatus::Published,
            'base_price_minor' => 10000,
            'base_price_currency' => 'EGP',
        ]);
    }
}

it('blocks the 6th rental service for a Free-tier vendor', function (): void {
    $vendor = makeFreeVendor();
    fillServices($vendor, ProductType::Rental, 5);

    $dto = new CreateRentalServiceDTO(
        vendorProfileId: $vendor->id,
        categoryId: Category::factory()->create(['allowed_product_types' => ['rental']])->id,
        name: ['en' => 'Six', 'ar' => 'ستة'],
        shortDescription: ['en' => 'd', 'ar' => 'd'],
        basePriceMinor: 10000,
        requiresElectricity: false,
        requiresOutdoorSpace: false,
        defaultRentalDurationHours: 4,
        setupTimeMinutes: null,
        teardownTimeMinutes: null,
        securityDepositMinor: null,
        minimumSpaceSqm: null,
    );

    expect(fn () => app(CreateRentalServiceAction::class)->execute($dto))
        ->toThrow(SubscriptionLimitReachedException::class);
})->group('subscriptions', 'us1', 'rental');

it('blocks the 6th sale service for a Free-tier vendor', function (): void {
    $vendor = makeFreeVendor();
    fillServices($vendor, ProductType::Sale, 5);

    $dto = new CreateSaleServiceDTO(
        vendorProfileId: $vendor->id,
        categoryId: Category::factory()->create(['allowed_product_types' => ['sale']])->id,
        name: ['en' => 'Six', 'ar' => 'ستة'],
        shortDescription: ['en' => 'd', 'ar' => 'd'],
        basePriceMinor: 10000,
        isPerishable: false,
        isMadeToOrder: false,
        leadTimeHours: 24,
        stockQuantity: null,
        customizationFields: null,
    );

    expect(fn () => app(CreateSaleServiceAction::class)->execute($dto))
        ->toThrow(SubscriptionLimitReachedException::class);
})->group('subscriptions', 'us1', 'sale');

it('blocks the 6th digital service for a Free-tier vendor', function (): void {
    $vendor = makeFreeVendor();
    fillServices($vendor, ProductType::Digital, 5);

    $dto = new CreateDigitalServiceDTO(
        vendorProfileId: $vendor->id,
        categoryId: Category::factory()->create(['allowed_product_types' => ['digital']])->id,
        name: ['en' => 'Six', 'ar' => 'ستة'],
        shortDescription: ['en' => 'd', 'ar' => 'd'],
        basePriceMinor: 10000,
        deliveryMethod: 'link',
        hasExpiry: false,
        expiryDaysAfterPurchase: null,
        isRefundableAfterDelivery: false,
        redemptionUrlTemplate: null,
    );

    expect(fn () => app(CreateDigitalServiceAction::class)->execute($dto))
        ->toThrow(SubscriptionLimitReachedException::class);
})->group('subscriptions', 'us1', 'digital');

it('exception names Silver as the unblocking tier (EN+AR)', function (): void {
    $vendor = makeFreeVendor();
    fillServices($vendor, ProductType::Rental, 5);

    $dto = new CreateRentalServiceDTO(
        vendorProfileId: $vendor->id,
        categoryId: Category::factory()->create(['allowed_product_types' => ['rental']])->id,
        name: ['en' => 'Six', 'ar' => 'ستة'],
        shortDescription: ['en' => 'd', 'ar' => 'd'],
        basePriceMinor: 10000,
        requiresElectricity: false,
        requiresOutdoorSpace: false,
        defaultRentalDurationHours: 4,
        setupTimeMinutes: null,
        teardownTimeMinutes: null,
        securityDepositMinor: null,
        minimumSpaceSqm: null,
    );

    try {
        app(CreateRentalServiceAction::class)->execute($dto);
    } catch (SubscriptionLimitReachedException $e) {
        expect($e->unblockingPlanCode)->toBe('silver')
            ->and($e->currentPlanCode)->toBe('free')
            ->and($e->limit)->toBe(5)
            ->and($e->currentCount)->toBe(5);

        // EN locale render
        app()->setLocale('en');
        $req = Request::create('/test', 'POST', [], [], [], ['HTTP_Accept-Language' => 'en']);
        $resp = $e->render($req);
        expect($resp->getStatusCode())->toBe(422);
        $payload = $resp->getData(true);
        expect($payload['errors'][0]['code'])->toBe('subscription_limit_reached');

        // AR locale render
        $reqAr = Request::create('/test', 'POST', [], [], [], ['HTTP_Accept-Language' => 'ar']);
        $respAr = $e->render($reqAr);
        $payloadAr = $respAr->getData(true);
        expect($payloadAr['errors'][0]['code'])->toBe('subscription_limit_reached');

        return;
    }

    $this->fail('Expected SubscriptionLimitReachedException was not thrown.');
})->group('subscriptions', 'us1');
