<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum HomeBlockType: string
{
    case HeroCarousel = 'hero_carousel';
    case FeaturedOccasions = 'featured_occasions';
    case FeaturedCategories = 'featured_categories';
    case FeaturedServices = 'featured_services';
    case VendorSpotlight = 'vendor_spotlight';
    case CtaBanner = 'cta_banner';
    case TextImageSplit = 'text_image_split';
    case Testimonials = 'testimonials';
    case LoyaltyPromo = 'loyalty_promo';

    public function label(): string
    {
        return match ($this) {
            self::HeroCarousel => 'Hero carousel',
            self::FeaturedOccasions => 'Featured occasions',
            self::FeaturedCategories => 'Featured categories',
            self::FeaturedServices => 'Featured services',
            self::VendorSpotlight => 'Vendor spotlight',
            self::CtaBanner => 'CTA banner',
            self::TextImageSplit => 'Text + image split',
            self::Testimonials => 'Testimonials',
            self::LoyaltyPromo => 'Loyalty promo',
        };
    }
}
