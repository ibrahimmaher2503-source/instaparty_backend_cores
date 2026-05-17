<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions;

use App\Modules\Identity\Domain\Events\VendorApproved;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class ApproveVendorTransition extends Transition
{
    public function __construct(
        private readonly VendorProfile $model,
        private readonly int $actorId,
    ) {}

    public function handle(): VendorProfile
    {
        abort_unless(
            auth()->user()?->can('approve_vendor_profile'),
            403,
            'Insufficient permissions to approve vendor'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        $this->model->approved_at = now();
        $this->model->approved_by = $this->actorId;
        $this->model->save();

        $this->model->approval_status->transitionTo(ApprovedState::class);

        DB::afterCommit(fn () => event(new VendorApproved($this->model)));

        return $this->model;
    }
}
