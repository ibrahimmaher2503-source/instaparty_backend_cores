<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\ServiceArchived;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveServiceAction
{
    public function execute(Service $service, User $admin): Service
    {
        $service->refresh();

        if (! $service->status->canTransitionTo(ServiceStatus::Archived)) {
            throw ValidationException::withMessages([
                'status' => __('catalog.moderation_invalid_transition'),
            ]);
        }

        if (! app(ServicePolicy::class)->archive($admin, $service)) {
            throw new AuthorizationException(__('catalog.moderation_not_allowed'));
        }

        return DB::transaction(function () use ($service): Service {
            $service->update(['status' => ServiceStatus::Archived]);

            DB::afterCommit(fn () => event(new ServiceArchived($service->refresh())));

            return $service;
        });
    }
}
