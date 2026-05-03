<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\DTOs;

final readonly class SubmitReviewData
{
    public function __construct(
        public string $bookingSubjectPublicId,
        public int $userId,
        public int $rating,
        public ?string $body,
        public string $locale,
    ) {}
}
