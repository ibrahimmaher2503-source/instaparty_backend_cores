<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

use App\Modules\Communication\Domain\Enums\ChatFreezeCategory;

final readonly class FreezeChatDTO
{
    public function __construct(
        public string $reasonEn,
        public string $reasonAr,
        public ChatFreezeCategory $category,
    ) {}
}
