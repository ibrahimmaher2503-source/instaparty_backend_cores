<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Contracts;

use App\Modules\Tax\Domain\Models\TaxRate;
use Illuminate\Support\Collection;

interface TaxRateRepository
{
    public function create(array $data): TaxRate;

    public function update(TaxRate $taxRate, array $data): TaxRate;

    /** @return Collection<int, TaxRate> */
    public function activeForDate(\DateTimeInterface $date): Collection;
}
