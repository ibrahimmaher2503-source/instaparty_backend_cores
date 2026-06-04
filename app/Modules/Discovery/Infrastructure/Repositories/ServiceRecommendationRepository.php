<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use Illuminate\Support\Collection;

/**
 * Context-scored service recommendations (Phase 3 ruling #1 — replaces the
 * descoped Phase 2 packages endpoints). Weights per the task-prompt scoring
 * spec: occasion 40 / city 30 / age 20 / price-fit 10.
 *
 * Age contributes 0 for every service in Phase 1: services carry no age
 * range columns (LOCKED schema), so the parameter is accepted but cannot
 * discriminate. Relative ranking is unaffected.
 */
class ServiceRecommendationRepository
{
    /** @return Collection<int, Service> */
    public function recommend(?int $occasionId, ?int $cityId, ?int $budgetMinor, int $limit = 10): Collection
    {
        $score = '(CASE WHEN ? IS NOT NULL AND category_id IN '
            .'(SELECT category_id FROM occasion_category WHERE occasion_id = ?) THEN 40 ELSE 0 END) + '
            .'(CASE WHEN ? IS NOT NULL AND vendor_profile_id IN '
            .'(SELECT vendor_profile_id FROM vendor_coverage_areas WHERE city_id = ?) THEN 30 ELSE 0 END) + '
            .'(CASE WHEN ? IS NOT NULL AND base_price_minor <= ? THEN 10 ELSE 0 END)';

        return Service::query()
            ->whereState('status', PublishedState::class)
            ->with(['category', 'vendor'])
            ->selectRaw("services.*, {$score} AS recommendation_score", [
                $occasionId, $occasionId,
                $cityId, $cityId,
                $budgetMinor, $budgetMinor,
            ])
            ->orderByDesc('recommendation_score')
            ->orderByDesc('rating_avg')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
