<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Domain\Enums;

enum PlacementType: string
{
    case HomepageBanner   = 'homepage_banner';
    case CategoryBanner   = 'category_banner';
    case SearchSponsored  = 'search_sponsored';
    case FeaturedListing  = 'featured_listing';

    public function label(): string
    {
        return match ($this) {
            self::HomepageBanner  => 'Homepage Banner',
            self::CategoryBanner  => 'Category Banner',
            self::SearchSponsored => 'Search Sponsored',
            self::FeaturedListing => 'Featured Listing',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::HomepageBanner  => 'warning',
            self::CategoryBanner  => 'info',
            self::SearchSponsored => 'success',
            self::FeaturedListing => 'danger',
        };
    }
}
