<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

final readonly class DecideServiceChangeRequestDTO
{
    /**
     * @param array{en: string, ar: string}|null $adminNote Bilingual admin note; required on reject/clarification
     */
    public function __construct(
        public int $adminUserId,
        public ?array $adminNote,
        public int $version,
    ) {}
}
