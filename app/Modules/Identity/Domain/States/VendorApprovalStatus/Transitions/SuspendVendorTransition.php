<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions;

use App\Modules\Identity\Domain\Events\VendorSuspended;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class SuspendVendorTransition extends Transition
{
    public function __construct(
        private readonly VendorProfile $model,
        private readonly int $actorId,
        private readonly string $reason,
    ) {}

    public function handle(): VendorProfile
    {
        abort_unless(
            auth()->user()?->can('suspend_vendor_profile'),
            403,
            'Insufficient permissions to suspend vendor'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);
        Context::add('transition_reason', $this->reason);

        $this->model->suspended_at = now();
        $this->model->suspended_by = $this->actorId;
        $this->model->save();

        $this->model->approval_status->transitionTo(SuspendedState::class);

        DB::afterCommit(fn () => event(new VendorSuspended($this->model)));

        return $this->model;
    }
}
