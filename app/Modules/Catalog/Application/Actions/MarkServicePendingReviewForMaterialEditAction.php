<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\ServiceReturnedToReview;
use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkServicePendingReviewForMaterialEditAction
{
    /** @var list<string> */
    private const MATERIAL_FIELDS = [
        'base_price_minor',
        'base_price_currency',
        'category_id',
        'name',
        'short_description',
        'long_description',
    ];

    public function execute(Service $service, bool $coreMediaChanged = false): Service
    {
        if ($service->status !== ServiceStatus::Published) {
            return $service;
        }

        if (! $coreMediaChanged && ! $service->wasChanged(self::MATERIAL_FIELDS)) {
            return $service;
        }

        if (! $service->status->canTransitionTo(ServiceStatus::PendingReview)) {
            throw ValidationException::withMessages([
                'status' => __('catalog.moderation_invalid_transition'),
            ]);
        }

        return DB::transaction(function () use ($service): Service {
            $service->update(['status' => ServiceStatus::PendingReview]);

            DB::afterCommit(fn () => event(new ServiceReturnedToReview($service->refresh())));

            return $service;
        });
    }
}
