<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\States\ServiceStatus\Transitions;

use App\Modules\Catalog\Domain\Events\ServicePublished;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class ApproveServiceTransition extends Transition
{
    public function __construct(
        private readonly Service $model,
        private readonly int $actorId,
    ) {}

    public function handle(): Service
    {
        abort_unless(
            auth()->user()?->can('publish_' . $this->model->product_type->value . '_service'),
            403,
            'Insufficient permissions to approve service'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        $this->model->moderated_at = now();
        $this->model->moderated_by = $this->actorId;
        $this->model->status = PublishedState::class;
        $this->model->save();

        DB::afterCommit(fn () => event(new ServicePublished($this->model)));

        return $this->model;
    }
}
