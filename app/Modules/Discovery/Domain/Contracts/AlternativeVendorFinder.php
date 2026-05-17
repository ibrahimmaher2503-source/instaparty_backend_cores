<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Domain\Contracts;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Collection;

interface AlternativeVendorFinder
{
    /**
     * @return Collection<int, VendorProfile>
     */
    public function findCandidates(Booking $booking, ?int $limit = null): Collection;

    /**
     * @param array<int> $vendorProfileIds
     * @throws \InvalidArgumentException if any ID fails the candidate filter
     */
    public function validateCandidates(Booking $booking, array $vendorProfileIds): void;
}
