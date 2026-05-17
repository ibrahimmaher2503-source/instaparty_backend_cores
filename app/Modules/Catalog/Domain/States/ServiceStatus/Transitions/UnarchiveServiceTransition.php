<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\States\ServiceStatus\Transitions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class UnarchiveServiceTransition extends Transition
{
    public function __construct(
        private readonly Service $model,
        private readonly int $actorId,
    ) {}

    public function handle(): Service
    {
        $user = auth()->user();
        $isOwner = $user?->vendorProfile?->id === $this->model->vendor_profile_id;
        $isAdmin = $user?->hasRole('admin');

        abort_unless(
            $isOwner || $isAdmin,
            403,
            'Insufficient permissions to unarchive service'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', $isAdmin ? TriggerKind::Admin->value : TriggerKind::Vendor->value);

        $this->model->status = DraftState::class;
        $this->model->save();

        return $this->model;
    }
}
