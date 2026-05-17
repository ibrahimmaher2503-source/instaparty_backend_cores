<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class ResetVendorToPendingTransition extends Transition
{
    public function __construct(
        private readonly VendorProfile $model,
        private readonly int $actorId,
        private readonly string $reason,
    ) {}

    public function handle(): VendorProfile
    {
        abort_unless(
            auth()->user()?->can('approve_vendor_profile'),
            403,
            'Insufficient permissions to reset vendor status'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);
        Context::add('transition_reason', $this->reason);

        $this->model->approval_status->transitionTo(PendingState::class);

        return $this->model;
    }
}
