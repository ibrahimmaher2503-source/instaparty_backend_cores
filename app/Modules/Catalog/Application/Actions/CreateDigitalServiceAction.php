<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\CreateDigitalServiceDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\DigitalServiceCreated;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDigitalServiceAction
{
    public function execute(CreateDigitalServiceDTO $dto): Service
    {
        return DB::transaction(function () use ($dto): Service {
            $service = Service::create([
                'public_id'           => Str::ulid()->toBase32(),
                'vendor_profile_id'   => $dto->vendorProfileId,
                'category_id'         => $dto->categoryId,
                'product_type'        => ProductType::Digital,
                'name'                => $dto->name,
                'short_description'   => $dto->shortDescription,
                'slug'                => Str::slug($dto->name['en']) . '-' . Str::lower(Str::random(6)),
                'status'              => ServiceStatus::Draft,
                'base_price_minor'    => $dto->basePriceMinor,
                'base_price_currency' => 'EGP',
            ]);

            ServiceDigitalDetail::create([
                'service_id'                   => $service->id,
                'delivery_method'              => $dto->deliveryMethod,
                'has_expiry'                   => $dto->hasExpiry,
                'expiry_days_after_purchase'   => $dto->expiryDaysAfterPurchase,
                'is_refundable_after_delivery' => $dto->isRefundableAfterDelivery,
                'redemption_url_template'      => $dto->redemptionUrlTemplate,
            ]);

            $loaded = $service->load('digitalDetail');
            DB::afterCommit(fn () => event(new DigitalServiceCreated($loaded)));

            return $loaded;
        });
    }
}
