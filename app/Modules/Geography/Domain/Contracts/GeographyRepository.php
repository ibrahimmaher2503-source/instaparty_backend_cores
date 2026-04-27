<?php

declare(strict_types=1);

namespace App\Modules\Geography\Domain\Contracts;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use Illuminate\Database\Eloquent\Collection;

interface GeographyRepository
{
    public function findCityById(int $id): ?City;

    public function findCityByPublicId(string $publicId): ?City;

    public function findGovernorateByPublicId(string $publicId): ?Governorate;

    /** @return Collection<int, City> */
    public function listCitiesByGovernorate(int $governorateId): Collection;

    /** @return Collection<int, City> */
    public function searchCities(string $query): Collection;

    /** @return Collection<int, Governorate> */
    public function activeGovernorates(): Collection;
}
