<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class ReviewHidden
{
    use Dispatchable;

    public function __construct(
        public int $reviewId,
        public string $reviewPublicId,
        public string $reviewType,
        public int $subjectId,
        public int $moderatorId,
        public \DateTimeInterface $hiddenAt,
    ) {}
}
