<?php

declare(strict_types=1);

namespace App\Modules\Geography\Infrastructure\Repositories;

use App\Modules\Geography\Domain\Contracts\GeographyRepository;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use Illuminate\Database\Eloquent\Collection;

class EloquentGeographyRepository implements GeographyRepository
{
    public function findCityById(int $id): ?City
    {
        return City::query()->find($id);
    }

    public function findCityByPublicId(string $publicId): ?City
    {
        return City::query()->where('public_id', $publicId)->first();
    }

    public function findGovernorateByPublicId(string $publicId): ?Governorate
    {
        return Governorate::query()->where('public_id', $publicId)->first();
    }

    public function listCitiesByGovernorate(int $governorateId): Collection
    {
        return City::query()
            ->forGovernorate($governorateId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function searchCities(string $query): Collection
    {
        return City::query()
            ->active()
            ->where(function ($q) use ($query) {
                $q->where('name->en', 'like', "%{$query}%")
                    ->orWhere('name->ar', 'like', "%{$query}%");
            })
            ->orderBy('sort_order')
            ->limit(50)
            ->get();
    }

    public function activeGovernorates(): Collection
    {
        return Governorate::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
