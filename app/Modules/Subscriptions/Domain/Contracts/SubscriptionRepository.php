<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Contracts;

use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Pagination\LengthAwarePaginator;

interface SubscriptionRepository
{
    public function find(int $id): ?VendorSubscription;

    public function findByPublicId(string $publicId): ?VendorSubscription;

    /** Returns the effective subscription for the vendor (override row takes precedence if active). */
    public function currentForVendor(int $vendorProfileId): ?VendorSubscription;

    /** Returns the underlying non-override active subscription (for renewal logic). */
    public function currentPaidForVendor(int $vendorProfileId): ?VendorSubscription;

    public function listForAdmin(array $filters, int $perPage): LengthAwarePaginator;

    public function persist(VendorSubscription $subscription): VendorSubscription;
}
