<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\DTOs;

use App\Modules\Reviews\Domain\Enums\ModerationStatus;

final readonly class ModerateReviewData
{
    public function __construct(
        public ModerationStatus $toStatus,
        public int $moderatorId,
        public ?array $reason = null,
    ) {}
}
