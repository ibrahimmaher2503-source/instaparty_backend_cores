<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions;

use App\Modules\Identity\Domain\Events\VendorRejected;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\RejectedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class RejectVendorTransition extends Transition
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
            'Insufficient permissions to reject vendor'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        $this->model->rejected_at = now();
        $this->model->rejected_by = $this->actorId;
        if ($this->rejectionReason !== null) {
            $this->model->rejection_reason = $this->rejectionReason;
        }
        $this->model->save();

        $this->model->approval_status->transitionTo(RejectedState::class);

        DB::afterCommit(fn () => event(new VendorRejected($this->model)));

        return $this->model;
    }
}
