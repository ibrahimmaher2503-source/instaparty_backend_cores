<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Repositories;

use App\Modules\Subscriptions\Domain\Contracts\SubscriptionRepository;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentSubscriptionRepository implements SubscriptionRepository
{
    public function find(int $id): ?VendorSubscription
    {
        return VendorSubscription::find($id);
    }

    public function findByPublicId(string $publicId): ?VendorSubscription
    {
        return VendorSubscription::where('public_id', $publicId)->with('plan')->first();
    }

    public function currentForVendor(int $vendorProfileId): ?VendorSubscription
    {
        // Admin override takes precedence; fall back to regular active/past_due subscription
        $override = VendorSubscription::where('vendor_profile_id', $vendorProfileId)
            ->where('is_admin_override', true)
            ->where('status', SubscriptionStatus::Active->value)
            ->with('plan')
            ->latest('started_at')
            ->first();

        if ($override !== null) {
            return $override;
        }

        return $this->currentPaidForVendor($vendorProfileId);
    }

    public function currentPaidForVendor(int $vendorProfileId): ?VendorSubscription
    {
        return VendorSubscription::where('vendor_profile_id', $vendorProfileId)
            ->where('is_admin_override', false)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::PastDue->value,
            ])
            ->with('plan')
            ->latest('started_at')
            ->first();
    }

    public function listForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = VendorSubscription::with(['plan', 'vendorProfile:id,public_id,display_name'])
            ->latest('started_at');

        if (isset($filters['plan_code'])) {
            $query->whereHas('plan', fn ($q) => $q->where('plan_code', $filters['plan_code']));
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['renews_before'])) {
            $query->where('current_period_end', '<=', $filters['renews_before']);
        }

        if (isset($filters['vendor_profile_public_id'])) {
            $query->whereHas('vendorProfile', fn ($q) => $q->where('public_id', $filters['vendor_profile_public_id']));
        }

        return $query->paginate($perPage);
    }

    public function persist(VendorSubscription $subscription): VendorSubscription
    {
        $subscription->save();

        return $subscription->fresh(['plan']);
    }
}
