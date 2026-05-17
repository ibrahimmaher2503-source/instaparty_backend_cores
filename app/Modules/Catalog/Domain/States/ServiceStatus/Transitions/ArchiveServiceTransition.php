<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\States\ServiceStatus\Transitions;

use App\Modules\Catalog\Domain\Events\ServiceArchived;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ArchivedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class ArchiveServiceTransition extends Transition
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
            'Insufficient permissions to archive service'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', $isAdmin ? TriggerKind::Admin->value : TriggerKind::Vendor->value);

        $this->model->status = ArchivedState::class;
        $this->model->save();

        DB::afterCommit(fn () => event(new ServiceArchived($this->model)));

        return $this->model;
    }
}
