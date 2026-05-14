<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\ServicePublished;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishServiceAction
{
    public function execute(Service $service, User $admin): Service
    {
        $service->refresh();

        if (! $service->status->canTransitionTo(ServiceStatus::Published)) {
            throw ValidationException::withMessages([
                'status' => __('catalog.moderation_invalid_transition'),
            ]);
        }

        if (! app(ServicePolicy::class)->approve($admin, $service)) {
            throw new AuthorizationException(__('catalog.moderation_not_allowed'));
        }

        return DB::transaction(function () use ($service, $admin): Service {
            $service->update([
                'status' => ServiceStatus::Published,
                'moderation_notes' => null,
                'moderated_at' => now(),
                'moderated_by' => $admin->id,
            ]);

            DB::afterCommit(fn () => event(new ServicePublished($service->refresh())));

            return $service;
        });
    }
}
