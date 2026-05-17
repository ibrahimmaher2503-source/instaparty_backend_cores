<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\States\ServiceStatus\Transitions;

use App\Modules\Catalog\Domain\Events\ServiceRejected;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\RejectedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class RejectServiceTransition extends Transition
{
    public function __construct(
        private readonly Service $model,
        private readonly int $actorId,
        private readonly ?array $moderationNotes = null,
    ) {}

    public function handle(): Service
    {
        abort_unless(
            auth()->user()?->can('reject_service'),
            403,
            'Insufficient permissions to reject service'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        $this->model->moderated_at = now();
        $this->model->moderated_by = $this->actorId;
        if ($this->moderationNotes !== null) {
            $this->model->moderation_notes = $this->moderationNotes;
        }
        $this->model->status = RejectedState::class;
        $this->model->save();

        DB::afterCommit(fn () => event(new ServiceRejected($this->model)));

        return $this->model;
    }
}
