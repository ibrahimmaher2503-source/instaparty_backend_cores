<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

final readonly class EscalateChatFlagDTO
{
    public function __construct(
        public int $chatModerationFlagId,
        public string $severity, // 'info' | 'warning' | 'critical'
        public string $summaryEn,
        public string $summaryAr,
    ) {}
}
