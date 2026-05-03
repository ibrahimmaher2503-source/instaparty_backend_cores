<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class ReviewSelfDeleted
{
    use Dispatchable;

    public function __construct(
        public int $reviewId,
        public string $reviewType,
        public int $subjectId,
        public string $previousModerationStatus,
    ) {}
}
