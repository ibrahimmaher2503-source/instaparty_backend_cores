<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface ReviewModerationLogRepository
{
    /**
     * Append a single immutable row. The implementation MUST refuse update/delete.
     *
     * @param  array{en: string, ar: string}|null  $reason
     */
    public function append(
        string $reviewType,
        int $reviewId,
        ?string $fromStatus,
        string $toStatus,
        int $moderatorId,
        ?array $reason = null,
    ): void;
}
