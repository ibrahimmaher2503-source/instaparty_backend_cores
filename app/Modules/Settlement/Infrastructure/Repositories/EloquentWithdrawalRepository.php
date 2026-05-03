<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Repositories;

use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use Illuminate\Pagination\CursorPaginator;

class EloquentWithdrawalRepository
{
    public function findByPublicId(string $publicId, int $vendorProfileId): ?Withdrawal
    {
        return Withdrawal::where('public_id', $publicId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->first();
    }

    public function hasPending(int $vendorProfileId): bool
    {
        return Withdrawal::where('vendor_profile_id', $vendorProfileId)
            ->where('status', WithdrawalStatus::Pending)
            ->exists();
    }

    /**
     * @return CursorPaginator<int, Withdrawal>
     */
    public function paginateForVendor(
        int $vendorProfileId,
        ?string $status,
        int $perPage = 20
    ): CursorPaginator {
        $query = Withdrawal::where('vendor_profile_id', $vendorProfileId)
            ->orderByDesc('requested_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->cursorPaginate($perPage);
    }
}
