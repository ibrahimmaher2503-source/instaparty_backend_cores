<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Http\Resources;

use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VendorSubscription
 */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => is_object($this->status) ? (string) $this->status : $this->status,
            'billing_cycle' => $this->billing_cycle?->value,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toIso8601String(),
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
            'is_admin_override' => (bool) $this->is_admin_override,
            'override_expires_at' => $this->override_expires_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
        ];
    }
}
