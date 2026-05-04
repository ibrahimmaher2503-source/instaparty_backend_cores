<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Infrastructure\Repositories;

use App\Modules\Reviews\Domain\Contracts\ReviewModerationLogRepository;
use App\Modules\Reviews\Domain\Models\ReviewModerationLog;

class EloquentReviewModerationLogRepository implements ReviewModerationLogRepository
{
    public function append(
        string $reviewType,
        int $reviewId,
        ?string $fromStatus,
        string $toStatus,
        int $moderatorId,
        ?array $reason = null,
    ): void {
        ReviewModerationLog::create([
            'review_type' => $reviewType,
            'review_id' => $reviewId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'moderator_id' => $moderatorId,
            'reason' => $reason,
        ]);
    }
}
