<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Application\DTOs\SearchServicesDTO;
use App\Modules\Discovery\Domain\Contracts\SearchRepository;
use App\Modules\Discovery\Domain\Events\ServiceSearchPerformed;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SearchServicesAction
{
    public function __construct(private readonly SearchRepository $searchRepository) {}

    public function execute(SearchServicesDTO $dto): LengthAwarePaginator
    {
        // The Scout path below filters/sorts on Meilisearch index attributes
        // (price_minor, is_active, coverage_city_ids…) that do not exist as
        // DB columns. Any other driver (null, database, collection) must use
        // the SQL fallback — under scout:database the index attributes leak
        // into SQL and 500 (SRCH-001).
        if ($dto->eventDate !== null || config('scout.driver') !== 'meilisearch') {
            return $this->executeDatabaseFallback($dto);
        }

        $builder = Service::search($dto->query ?? '');
        // `is_active` in the index is a pure mirror of the published lifecycle
        // state (Service::toSearchableArray + shouldBeSearchable). Only published
        // services are ever indexed, so this filter can never diverge from the
        // admin-driven published state.
        $builder->where('is_active', true);

        // Hydrate the canonical contract's relations on the models Scout
        // retrieves from the DB, so the result resource never N+1s.
        $builder->query(fn (Builder $query) => $query->with([
            'media',
            'category',
            'vendor.primaryCity',
            'vendor.primaryGovernorate',
            'rentalDetail',
            'saleDetail',
            'digitalDetail',
        ]));

        if ($dto->type !== null) {
            $builder->where('product_type', $dto->type->value);
        }

        if ($dto->categorySlug !== null) {
            $categoryIds = $this->searchRepository->resolveCategoryIds($dto->categorySlug);
            if ($categoryIds !== []) {
                $builder->whereIn('category_id', $categoryIds);
            }
        }

        if ($dto->occasionCode !== null) {
            $occasionId = $this->searchRepository->resolveOccasionId($dto->occasionCode);
            // An unknown occasion matches nothing — never silently drop the filter.
            $builder->where('occasion_ids', $occasionId ?? -1);
        }

        if ($dto->vendorPublicId !== null) {
            $vendorId = $this->searchRepository->resolveVendorId($dto->vendorPublicId);
            // An unknown vendor matches nothing — never silently drop the filter.
            $builder->where('vendor_id', $vendorId ?? -1);
        }

        if ($dto->cityPublicId !== null) {
            $cityId = $this->searchRepository->resolveCityId($dto->cityPublicId);
            if ($cityId !== null) {
                $builder->where('coverage_city_ids', $cityId);
            }
        }

        if ($dto->priceMin !== null) {
            $builder->where('price_minor', '>=', $dto->priceMin);
        }

        if ($dto->priceMax !== null) {
            $builder->where('price_minor', '<=', $dto->priceMax);
        }

        if ($dto->minRating !== null) {
            $builder->where('rating_avg', '>=', $dto->minRating);
        }

        // Scout's orderBy(column, direction) takes two arguments — the engine
        // assembles the `column:direction` Meilisearch spec itself. Passing a
        // combined string here was SRCH-001.
        match ($dto->sort) {
            'price_asc' => $builder->orderBy('price_minor', 'asc'),
            'price_desc' => $builder->orderBy('price_minor', 'desc'),
            'rating_desc' => $builder->orderBy('rating_avg', 'desc'),
            'newest' => $builder->orderBy('id', 'desc'),
            null => null,
        };

        $results = $builder->paginate($dto->perPage, 'page', $dto->page);

        event(new ServiceSearchPerformed(
            query: $dto->query,
            locale: $dto->locale,
            // Drop only NULL (unset) filters — keep explicit 0 values
            // (price_min=0 / min_rating=0 are real filters, not "unset").
            filtersApplied: array_filter([
                'type' => $dto->type?->value,
                'occasion' => $dto->occasionCode,
                'category' => $dto->categorySlug,
                'vendor' => $dto->vendorPublicId,
                'city' => $dto->cityPublicId,
                'price_min' => $dto->priceMin,
                'price_max' => $dto->priceMax,
                'min_rating' => $dto->minRating,
            ], static fn ($v): bool => $v !== null),
            resultsCount: $results->total(),
            userId: auth()->id(),
        ));

        return $results;
    }

    private function executeDatabaseFallback(SearchServicesDTO $dto): LengthAwarePaginator
    {
        $locale = $dto->locale === 'ar' ? 'ar' : 'en';
        $builder = Service::query()
            ->published()
            ->with(['media', 'category.occasions', 'vendor.primaryCity', 'vendor.primaryGovernorate', 'rentalDetail', 'saleDetail', 'digitalDetail']);

        if ($dto->query !== null && trim($dto->query) !== '') {
            $query = '%'.trim($dto->query).'%';
            $builder->where(function ($q) use ($query, $locale): void {
                $q->where("name->{$locale}", 'like', $query)
                    ->orWhere("short_description->{$locale}", 'like', $query)
                    ->orWhere("long_description->{$locale}", 'like', $query);
            });
        }

        if ($dto->type !== null) {
            $builder->where('product_type', $dto->type->value);
        }

        if ($dto->categorySlug !== null) {
            $categoryIds = $this->searchRepository->resolveCategoryIds($dto->categorySlug);
            $builder->when($categoryIds !== [], fn ($q) => $q->whereIn('category_id', $categoryIds));
        }

        if ($dto->occasionCode !== null) {
            $occasionId = $this->searchRepository->resolveOccasionId($dto->occasionCode);
            // An unknown occasion matches nothing — never silently drop the filter.
            $builder->whereHas('category.occasions', fn ($oq) => $oq->whereKey($occasionId ?? -1));
        }

        if ($dto->vendorPublicId !== null) {
            $vendorId = $this->searchRepository->resolveVendorId($dto->vendorPublicId);
            // An unknown vendor matches nothing — never silently drop the filter.
            $builder->where('vendor_profile_id', $vendorId ?? -1);
        }

        if ($dto->cityPublicId !== null) {
            $cityId = $this->searchRepository->resolveCityId($dto->cityPublicId);
            // A service is available in a city when its vendor's primary city
            // matches OR the vendor lists the city among its coverage areas.
            $builder->when($cityId !== null, fn ($q) => $q->whereHas('vendor', fn ($vq) => $vq
                ->where('primary_city_id', $cityId)
                ->orWhereHas('coverageAreas', fn ($cq) => $cq->where('city_id', $cityId))));
        }

        if ($dto->priceMin !== null) {
            $builder->where('base_price_minor', '>=', $dto->priceMin);
        }

        if ($dto->priceMax !== null) {
            $builder->where('base_price_minor', '<=', $dto->priceMax);
        }

        if ($dto->minRating !== null) {
            $builder->where('rating_avg', '>=', $dto->minRating);
        }

        if ($dto->eventDate !== null) {
            $startsAt = $dto->eventDate->startOfDay();
            $endsAt = $dto->eventDate->endOfDay();

            $builder->where(static function (Builder $query) use ($startsAt, $endsAt): void {
                $query
                    ->where('product_type', '!=', ProductType::Rental->value)
                    ->orWhereDoesntHave('availabilityBlocks', static fn (Builder $blocks) => $blocks
                        ->where('starts_at', '<=', $endsAt)
                        ->where('ends_at', '>=', $startsAt));
            });
        }

        match ($dto->sort) {
            'price_asc' => $builder->orderBy('base_price_minor'),
            'price_desc' => $builder->orderByDesc('base_price_minor'),
            'rating_desc' => $builder->orderByDesc('rating_avg'),
            'newest' => $builder->orderByDesc('id'),
            null => $builder->orderByDesc('is_featured')->orderByDesc('id'),
        };

        $results = $builder->paginate(
            $dto->perPage,
            ['*'],
            'page',
            $dto->page,
        );

        event(new ServiceSearchPerformed(
            query: $dto->query,
            locale: $dto->locale,
            // Drop only NULL (unset) filters — keep explicit 0 values
            // (price_min=0 / min_rating=0 are real filters, not "unset").
            filtersApplied: array_filter([
                'type' => $dto->type?->value,
                'occasion' => $dto->occasionCode,
                'category' => $dto->categorySlug,
                'vendor' => $dto->vendorPublicId,
                'city' => $dto->cityPublicId,
                'price_min' => $dto->priceMin,
                'price_max' => $dto->priceMax,
                'min_rating' => $dto->minRating,
                'event_date' => $dto->eventDate?->toDateString(),
            ], static fn ($v): bool => $v !== null),
            resultsCount: $results->total(),
            userId: auth()->id(),
        ));

        return $results;
    }
}
