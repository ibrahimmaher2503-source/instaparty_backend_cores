<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

final readonly class UnfreezeChatDTO
{
    public function __construct(
        public string $reasonEn,
        public string $reasonAr,
    ) {}
}
