<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ChangesRequestedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class RequestVendorChangesTransition extends Transition
{
    public function __construct(
        private readonly VendorProfile $model,
        private readonly int $actorId,
        private readonly ?array $rejectionReason = null,
    ) {}

    public function handle(): VendorProfile
    {
        abort_unless(
            auth()->user()?->can('approve_vendor_profile'),
            403,
            'Insufficient permissions to request vendor changes'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        if ($this->rejectionReason !== null) {
            $this->model->rejection_reason = $this->rejectionReason;
            $this->model->save();
        }

        $this->model->approval_status->transitionTo(ChangesRequestedState::class);

        return $this->model;
    }
}
