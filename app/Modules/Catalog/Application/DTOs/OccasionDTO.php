<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

final class OccasionDTO
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>|null  $description
     */
    public function __construct(
        public readonly string $code,
        public readonly array $name,
        public readonly ?array $description = null,
        public readonly ?string $iconPath = null,
        public readonly int $sortOrder = 0,
        public readonly bool $isActive = true,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            name: (array) $data['name'],
            description: isset($data['description']) ? (array) $data['description'] : null,
            iconPath: $data['icon_path'] ?? null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return array_filter([
            'code'        => $this->code,
            'name'        => $this->name,
            'description' => $this->description,
            'icon_path'   => $this->iconPath,
            'sort_order'  => $this->sortOrder,
            'is_active'   => $this->isActive,
        ], static fn ($v) => $v !== null);
    }
}
