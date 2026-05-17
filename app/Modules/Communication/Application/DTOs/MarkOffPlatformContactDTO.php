<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

use App\Modules\Communication\Domain\Enums\ChatFlagType;

final readonly class MarkOffPlatformContactDTO
{
    public function __construct(
        public int $chatMessageLogId,
        public ChatFlagType $flagType,
        public string $reasonEn,
        public string $reasonAr,
    ) {}
}
