<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\States\Transitions;

use App\Modules\Shared\Domain\Enums\TriggerKind;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Domain\States\CancelledState;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class CancelSubscriptionTransition extends Transition
{
    public function __construct(
        private readonly VendorSubscription $model,
        private readonly int $actorId,
        private readonly ?string $reason = null,
    ) {}

    public function handle(): VendorSubscription
    {
        $user = auth()->user();
        $isAdmin = $user?->hasRole('admin');
        $isVendorOwner = $user?->vendorProfile?->id === $this->model->vendor_profile_id;

        abort_unless($isAdmin || $isVendorOwner, 403, 'Insufficient permissions to cancel subscription');

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', $isAdmin ? TriggerKind::Admin->value : TriggerKind::Vendor->value);
        if ($this->reason !== null) {
            Context::add('transition_reason', $this->reason);
        }

        $this->model->ended_at = now();
        $this->model->ended_reason = $this->reason ?? 'cancelled';
        $this->model->save();

        $this->model->status->transitionTo(CancelledState::class);

        return $this->model;
    }
}
