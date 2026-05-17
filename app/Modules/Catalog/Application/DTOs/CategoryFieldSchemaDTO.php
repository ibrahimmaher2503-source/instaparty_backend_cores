<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

use App\Modules\Catalog\Domain\Enums\ProductType;

final class CategoryFieldSchemaDTO
{
    /**
     * @param  array<string, string>  $fieldLabel
     * @param  array<int, string>|null  $options
     * @param  array<int, string>|null  $validationRules
     */
    public function __construct(
        public readonly int $categoryId,
        public readonly ProductType $productType,
        public readonly string $fieldKey,
        public readonly array $fieldLabel,
        public readonly string $fieldType,
        public readonly ?array $options = null,
        public readonly bool $isRequired = false,
        public readonly bool $isFilterable = false,
        public readonly ?array $validationRules = null,
        public readonly int $sortOrder = 0,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            categoryId: (int) $data['category_id'],
            productType: $data['product_type'] instanceof ProductType
                ? $data['product_type']
                : ProductType::from((string) $data['product_type']),
            fieldKey: (string) $data['field_key'],
            fieldLabel: (array) $data['field_label'],
            fieldType: (string) $data['field_type'],
            options: isset($data['options']) ? (array) $data['options'] : null,
            isRequired: (bool) ($data['is_required'] ?? false),
            isFilterable: (bool) ($data['is_filterable'] ?? false),
            validationRules: isset($data['validation_rules']) ? (array) $data['validation_rules'] : null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'category_id'       => $this->categoryId,
            'product_type'      => $this->productType->value,
            'field_key'         => $this->fieldKey,
            'field_label'       => $this->fieldLabel,
            'field_type'        => $this->fieldType,
            'options'           => $this->options,
            'is_required'       => $this->isRequired,
            'is_filterable'     => $this->isFilterable,
            'validation_rules'  => $this->validationRules,
            'sort_order'        => $this->sortOrder,
        ];
    }
}
