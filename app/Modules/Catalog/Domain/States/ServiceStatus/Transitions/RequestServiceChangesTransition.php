<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\States\ServiceStatus\Transitions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ChangesRequestedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class RequestServiceChangesTransition extends Transition
{
    public function __construct(
        private readonly Service $model,
        private readonly int $actorId,
        private readonly ?array $moderationNotes = null,
    ) {}

    public function handle(): Service
    {
        abort_unless(
            auth()->user()?->can('request_service_edits'),
            403,
            'Insufficient permissions to request service changes'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        $this->model->moderated_at = now();
        $this->model->moderated_by = $this->actorId;
        if ($this->moderationNotes !== null) {
            $this->model->moderation_notes = $this->moderationNotes;
        }
        $this->model->status = ChangesRequestedState::class;
        $this->model->save();

        return $this->model;
    }
}
