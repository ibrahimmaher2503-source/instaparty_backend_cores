<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\CreateSaleServiceDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\SaleServiceCreated;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionPolicyContract;
use App\Modules\Subscriptions\Domain\Exceptions\SubscriptionLimitReachedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSaleServiceAction
{
    public function __construct(private readonly SubscriptionPolicyContract $subscriptionPolicy) {}

    public function execute(CreateSaleServiceDTO $dto): Service
    {
        $decision = $this->subscriptionPolicy->canCreateService($dto->vendorProfileId, ProductType::Sale);
        if (! $decision->allowed) {
            throw new SubscriptionLimitReachedException(
                $decision->featureKey,
                $decision->currentCount,
                $decision->limit,
                $decision->unblockingPlanCode,
                $decision->currentPlanCode,
            );
        }

        return DB::transaction(function () use ($dto): Service {
            $service = Service::create([
                'public_id' => Str::ulid()->toBase32(),
                'vendor_profile_id' => $dto->vendorProfileId,
                'category_id' => $dto->categoryId,
                'product_type' => ProductType::Sale,
                'name' => $dto->name,
                'short_description' => $dto->shortDescription,
                'slug' => Str::slug($dto->name['en']).'-'.Str::lower(Str::random(6)),
                'status' => ServiceStatus::Draft,
                'base_price_minor' => $dto->basePriceMinor,
                'base_price_currency' => 'EGP',
            ]);

            ServiceSaleDetail::create([
                'service_id' => $service->id,
                'is_perishable' => $dto->isPerishable,
                'is_made_to_order' => $dto->isMadeToOrder,
                'lead_time_hours' => $dto->leadTimeHours,
                'stock_quantity' => $dto->stockQuantity, // null = unlimited
                'customization_fields' => $dto->customizationFields,
            ]);

            $loaded = $service->load('saleDetail');
            DB::afterCommit(fn () => event(new SaleServiceCreated($loaded)));

            return $loaded;
        });
    }
}
