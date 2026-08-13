<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Web;

use App\Modules\Catalog\Application\Actions\ListPublicCategoriesAction;
use App\Modules\Catalog\Application\Actions\ListPublicOccasionsAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Discovery\Application\Actions\SearchServicesAction;
use App\Modules\Discovery\Application\DTOs\SearchServicesDTO;
use App\Modules\Discovery\Infrastructure\Repositories\VendorBrowsingRepository;
use App\Modules\Geography\Domain\Contracts\GeographyRepository;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SearchController
{
    public function __invoke(
        Request $request,
        SearchServicesAction $search,
        ListPublicCategoriesAction $categories,
        ListPublicOccasionsAction $occasions,
        VendorBrowsingRepository $vendors,
        GeographyRepository $geography,
    ): View {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', Rule::enum(ProductType::class)],
            'category_slug' => ['nullable', 'string', 'max:120'],
            'occasion' => ['nullable', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:26'],
            'city_public_id' => ['nullable', 'string', 'max:26'],
            'event_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0', 'gte:price_min'],
            'min_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc', 'rating_desc', 'newest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $type = isset($filters['product_type'])
            ? ProductType::from($filters['product_type'])
            : null;
        $results = $search->execute(new SearchServicesDTO(
            query: $filters['q'] ?? null,
            type: $type,
            categorySlug: $filters['category_slug'] ?? null,
            occasionCode: $filters['occasion'] ?? null,
            vendorPublicId: $filters['vendor'] ?? null,
            cityPublicId: $filters['city_public_id'] ?? null,
            priceMin: isset($filters['price_min'])
                ? Money::of((string) $filters['price_min'], 'EGP')->getMinorAmount()->toInt()
                : null,
            priceMax: isset($filters['price_max'])
                ? Money::of((string) $filters['price_max'], 'EGP')->getMinorAmount()->toInt()
                : null,
            minRating: isset($filters['min_rating']) ? (float) $filters['min_rating'] : null,
            locale: app()->getLocale(),
            page: max(1, (int) ($filters['page'] ?? 1)),
            perPage: 12,
            sort: $filters['sort'] ?? null,
            eventDate: isset($filters['event_date'])
                ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['event_date'], 'UTC')
                : null,
        ));

        $results->withQueryString();

        return view('storefront.search', [
            'services' => $results,
            'categories' => $categories->execute(),
            'occasions' => $occasions->execute(),
            'vendors' => $vendors->approvedForFilter(),
            'cities' => $geography->searchCities(''),
            'filters' => $filters,
        ]);
    }
}
