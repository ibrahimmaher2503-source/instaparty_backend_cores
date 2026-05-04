<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Importers;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DigitalServicesImport implements ToCollection, WithHeadingRow
{
    /** @var Collection<int, Collection<string, mixed>> */
    private Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    /** @param Collection<int, Collection<string, mixed>> $collection */
    public function collection(Collection $collection): void
    {
        $this->rows = $collection;
    }

    /** @return Collection<int, Collection<string, mixed>> */
    public function getRows(): Collection
    {
        return $this->rows;
    }
}
