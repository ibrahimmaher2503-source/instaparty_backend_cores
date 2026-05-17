<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Repositories;

use App\Modules\Tax\Domain\Contracts\TaxRateRepository;
use App\Modules\Tax\Domain\Models\TaxRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EloquentTaxRateRepository implements TaxRateRepository
{
    public function create(array $data): TaxRate
    {
        return TaxRate::create(array_merge($data, ['public_id' => Str::ulid()]));
    }

    public function update(TaxRate $taxRate, array $data): TaxRate
    {
        $taxRate->update($data);
        return $taxRate->fresh();
    }

    public function activeForDate(\DateTimeInterface $date): Collection
    {
        return TaxRate::query()
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $date))
            ->get();
    }
}
