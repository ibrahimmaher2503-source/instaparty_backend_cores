<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CloneServiceAction
{
    public function execute(Service $service, VendorProfile $vendorProfile): Service
    {
        if ($service->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['service' => __('catalog.errors.not_owned')]);
        }

        $approvedTypes = $vendorProfile->approvedTypes->pluck('product_type')->toArray();
        if (! in_array($service->product_type, $approvedTypes, true)) {
            throw ValidationException::withMessages(['product_type' => __('catalog.errors.not_approved_for_type')]);
        }

        return DB::transaction(function () use ($service): Service {
            $nameEn = $service->getTranslation('name', 'en') . ' (Copy)';
            $nameAr = $service->getTranslation('name', 'ar') . ' (نسخة)';

            $clone = Service::create([
                'public_id'            => Str::ulid()->toBase32(),
                'vendor_profile_id'    => $service->vendor_profile_id,
                'category_id'          => $service->category_id,
                'product_type'         => $service->product_type,
                'name'                 => ['en' => $nameEn, 'ar' => $nameAr],
                'short_description'    => $service->short_description,
                'long_description'     => $service->long_description,
                'slug'                 => Str::slug($nameEn) . '-' . Str::lower(Str::random(6)),
                'status'               => ServiceStatus::Draft,
                'base_price_minor'     => $service->base_price_minor,
                'base_price_currency'  => $service->base_price_currency,
            ]);

            $this->cloneDetailTable($service, $clone);
            $this->cloneMedia($service, $clone);

            return $clone;
        });
    }

    private function cloneDetailTable(Service $source, Service $clone): void
    {
        match ($source->product_type) {
            ProductType::Rental  => $this->cloneRentalDetail($source, $clone),
            ProductType::Sale    => $this->cloneSaleDetail($source, $clone),
            ProductType::Digital => $this->cloneDigitalDetail($source, $clone),
        };
    }

    private function cloneRentalDetail(Service $source, Service $clone): void
    {
        $detail = $source->rentalDetail;
        if ($detail === null) {
            return;
        }

        $clone->rentalDetail()->create($detail->only([
            'requires_electricity',
            'requires_outdoor_space',
            'default_rental_duration_hours',
            'setup_time_minutes',
            'teardown_time_minutes',
            'security_deposit_minor',
            'security_deposit_currency',
            'minimum_space_sqm',
        ]));
    }

    private function cloneSaleDetail(Service $source, Service $clone): void
    {
        $detail = $source->saleDetail;
        if ($detail === null) {
            return;
        }

        $clone->saleDetail()->create($detail->only([
            'is_perishable',
            'is_made_to_order',
            'lead_time_hours',
            'stock_quantity',
            'allows_customization',
            'customization_fields',
            'weight_grams',
            'dimensions_cm',
        ]));
    }

    private function cloneDigitalDetail(Service $source, Service $clone): void
    {
        $detail = $source->digitalDetail;
        if ($detail === null) {
            return;
        }

        $clone->digitalDetail()->create($detail->only([
            'delivery_method',
            'has_expiry',
            'expiry_days_after_purchase',
            'is_refundable_after_delivery',
            'redemption_url_template',
        ]));
    }

    private function cloneMedia(Service $source, Service $clone): void
    {
        foreach ($source->getMedia('gallery') as $media) {
            try {
                $media->copy($clone, 'gallery');
            } catch (\Throwable) {
                // Non-fatal: media copy failure should not block clone
            }
        }
    }
}
