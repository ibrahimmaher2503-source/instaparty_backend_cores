<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

use App\Modules\Catalog\Domain\Enums\ProductType;

final readonly class ServiceFieldDiff
{
    /**
     * @param list<array{field_path: string, field_classification: string, before_value: mixed, after_value: mixed, is_material: bool}> $items
     */
    public function __construct(
        public array $items,
        public ProductType $productType,
    ) {}

    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    public function hasMaterialChanges(): bool
    {
        foreach ($this->items as $item) {
            if ($item['is_material']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns only the material items.
     *
     * @return list<array{field_path: string, field_classification: string, before_value: mixed, after_value: mixed, is_material: bool}>
     */
    public function materialItems(): array
    {
        return array_values(array_filter($this->items, fn (array $i) => $i['is_material']));
    }
}
