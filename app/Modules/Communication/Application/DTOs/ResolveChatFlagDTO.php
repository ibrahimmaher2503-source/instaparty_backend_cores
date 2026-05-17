<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

use App\Modules\Communication\Domain\Enums\ChatFlagResolution;

final readonly class ResolveChatFlagDTO
{
    public function __construct(
        public ChatFlagResolution $decision,
        public string $noteEn,
        public string $noteAr,
    ) {}
}
