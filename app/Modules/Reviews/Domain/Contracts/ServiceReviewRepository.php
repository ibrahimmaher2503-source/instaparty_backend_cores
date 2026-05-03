<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

use App\Modules\Reviews\Application\DTOs\SubmitReviewData;
use App\Modules\Reviews\Domain\Models\ServiceReview;

interface ServiceReviewRepository
{
    public function create(SubmitReviewData $data, int $serviceId, int $bookingItemId): ServiceReview;

    public function findByPublicIdForUser(string $publicId, int $userId): ?ServiceReview;

    public function findByBookingItemId(int $bookingItemId): ?ServiceReview;

    /**
     * @param  array{cursor?: string|null, limit?: int}  $options
     * @return array{items: ServiceReview[], next_cursor: ?string, prev_cursor: ?string}
     */
    public function listApprovedForService(int $serviceId, array $options = []): array;

    /** @return array{average: float, count: int} */
    public function aggregateApprovedForService(int $serviceId): array;

    public function softDelete(ServiceReview $review): void;

    public function transition(ServiceReview $review, string $toStatus, int $moderatorId): void;
}
