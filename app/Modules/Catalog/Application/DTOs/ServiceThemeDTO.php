<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

final class ServiceThemeDTO
{
    /** @param  array<string, string>  $name */
    public function __construct(
        public readonly string $code,
        public readonly array $name,
        public readonly ?string $iconPath = null,
        public readonly bool $isActive = true,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            name: (array) $data['name'],
            iconPath: $data['icon_path'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return array_filter([
            'code'      => $this->code,
            'name'      => $this->name,
            'icon_path' => $this->iconPath,
            'is_active' => $this->isActive,
        ], static fn ($v) => $v !== null);
    }
}
