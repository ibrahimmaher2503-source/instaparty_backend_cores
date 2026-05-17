<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Policies;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceFieldClassification;

final class MaterialFieldRegistry
{
    /** Fields material for ALL product types */
    private const array SHARED = [
        'name.en',
        'name.ar',
        'short_description.en',
        'short_description.ar',
        'long_description.en',
        'long_description.ar',
        'base_price_minor',
        'base_price_currency',
        'category_id',
        'gallery_ops',
        'is_available_for_booking',
        'availability_windows',
        'excluded_dates',
        'pricing_tiers',
    ];

    /** Fields material only for Rental services (service_rental_details.*)  */
    private const array RENTAL = [
        'service_rental_details.security_deposit_minor',
        'service_rental_details.default_rental_duration_hours',
        'service_rental_details.setup_time_minutes',
        'service_rental_details.requires_electricity',
        'service_rental_details.requires_outdoor_space',
        'service_rental_details.min_age',
        'service_rental_details.max_capacity',
    ];

    /** Fields material only for Sale services (service_sale_details.*) */
    private const array SALE = [
        'service_sale_details.is_perishable',
        'service_sale_details.is_made_to_order',
        'service_sale_details.lead_time_hours',
        'service_sale_details.stock_quantity',
        'service_sale_details.customization_fields',
    ];

    /** Fields material only for Digital services (service_digital_details.*) */
    private const array DIGITAL = [
        'service_digital_details.delivery_method',
        'service_digital_details.has_expiry',
        'service_digital_details.expiry_days_after_purchase',
        'service_digital_details.is_refundable_after_delivery',
        'service_digital_details.redemption_url_template',
    ];

    /**
     * Returns the complete set of material field paths for the given product type.
     * Shared fields are always included; type-specific fields are appended by match.
     *
     * @return list<string>
     */
    public function materialFieldsFor(ProductType $type): array
    {
        $typeSpecific = match ($type) {
            ProductType::Rental => self::RENTAL,
            ProductType::Sale => self::SALE,
            ProductType::Digital => self::DIGITAL,
        };

        return array_merge(self::SHARED, $typeSpecific);
    }

    /**
     * Returns the classification for a given field path.
     */
    public function classifyField(string $fieldPath, ProductType $type): ServiceFieldClassification
    {
        if (str_starts_with($fieldPath, 'gallery_ops')) {
            return ServiceFieldClassification::Media;
        }

        if (in_array($fieldPath, ['availability_windows', 'excluded_dates'], true)) {
            return ServiceFieldClassification::Availability;
        }

        if ($fieldPath === 'pricing_tiers' || str_starts_with($fieldPath, 'pricing_tiers.')) {
            return ServiceFieldClassification::PricingTier;
        }

        $prefix = 'service_'.$type->value.'_details.';
        if (str_starts_with($fieldPath, $prefix)) {
            return match ($type) {
                ProductType::Rental => ServiceFieldClassification::Rental,
                ProductType::Sale => ServiceFieldClassification::Sale,
                ProductType::Digital => ServiceFieldClassification::Digital,
            };
        }

        return ServiceFieldClassification::Shared;
    }

    /**
     * Whether the given field path is a material field for the product type.
     */
    public function isMaterial(string $fieldPath, ProductType $type): bool
    {
        return in_array($fieldPath, $this->materialFieldsFor($type), true)
            || str_starts_with($fieldPath, 'gallery_ops')
            || str_starts_with($fieldPath, 'pricing_tiers.')
            || str_starts_with($fieldPath, 'availability_windows.')
            || str_starts_with($fieldPath, 'excluded_dates.');
    }
}
