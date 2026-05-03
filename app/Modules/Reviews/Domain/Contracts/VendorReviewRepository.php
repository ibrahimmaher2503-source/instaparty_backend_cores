<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

use App\Modules\Reviews\Application\DTOs\SubmitReviewData;
use App\Modules\Reviews\Domain\Models\VendorReview;

interface VendorReviewRepository
{
    public function create(SubmitReviewData $data, int $vendorProfileId, int $bookingVendorId): VendorReview;

    public function findByPublicIdForUser(string $publicId, int $userId): ?VendorReview;

    public function findByBookingVendorId(int $bookingVendorId): ?VendorReview;

    /**
     * @param  array{cursor?: string|null, limit?: int}  $options
     * @return array{items: VendorReview[], next_cursor: ?string, prev_cursor: ?string}
     */
    public function listApprovedForVendor(int $vendorProfileId, array $options = []): array;

    /** @return array{average: float, count: int} */
    public function aggregateApprovedForVendor(int $vendorProfileId): array;

    public function softDelete(VendorReview $review): void;

    public function transition(VendorReview $review, string $toStatus, int $moderatorId): void;
}
