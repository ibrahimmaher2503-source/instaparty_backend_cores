<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Application\DTOs\SearchServicesDTO;
use App\Modules\Discovery\Domain\Contracts\SearchRepository;
use App\Modules\Discovery\Domain\Events\ServiceSearchPerformed;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchServicesAction
{
    public function __construct(private readonly SearchRepository $searchRepository) {}

    public function execute(SearchServicesDTO $dto): LengthAwarePaginator
    {
        $builder = Service::search($dto->query ?? '');
        $builder->where('is_active', true);

        if ($dto->type !== null) {
            $builder->where('product_type', $dto->type->value);
        }

        if ($dto->categoryPublicId !== null) {
            $categoryId = $this->searchRepository->resolveCategoryId($dto->categoryPublicId);
            if ($categoryId !== null) {
                $builder->where('category_id', $categoryId);
            }
        }

        if ($dto->occasionCode !== null) {
            $occasionId = $this->searchRepository->resolveOccasionId($dto->occasionCode);
            if ($occasionId !== null) {
                $builder->where('occasion_ids', $occasionId);
            }
        }

        if ($dto->vendorPublicId !== null) {
            $vendorId = $this->searchRepository->resolveVendorId($dto->vendorPublicId);
            if ($vendorId !== null) {
                $builder->where('vendor_id', $vendorId);
            }
        }

        if ($dto->priceMax !== null) {
            $builder->where('price_minor', '<=', $dto->priceMax);
        }

        $results = $builder->paginate($dto->perPage, 'page', $dto->page);

        event(new ServiceSearchPerformed(
            query: $dto->query,
            locale: $dto->locale,
            filtersApplied: array_filter([
                'type'      => $dto->type?->value,
                'occasion'  => $dto->occasionCode,
                'category'  => $dto->categoryPublicId,
                'vendor'    => $dto->vendorPublicId,
                'price_max' => $dto->priceMax,
            ]),
            resultsCount: $results->total(),
            userId: auth()->id(),
        ));

        return $results;
    }
}
