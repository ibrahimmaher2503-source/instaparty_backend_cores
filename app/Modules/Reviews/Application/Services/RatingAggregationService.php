<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Services;

use Illuminate\Support\Facades\DB;

class RatingAggregationService
{
    public function recompute(string $subjectType, int $subjectId): array
    {
        $table = $subjectType === 'vendor' ? 'vendor_reviews' : 'service_reviews';
        $column = $subjectType === 'vendor' ? 'vendor_profile_id' : 'service_id';

        $result = DB::table($table)
            ->where($column, $subjectId)
            ->where('moderation_status', 'approved')
            ->whereNull('deleted_at')
            ->selectRaw('AVG(rating) as average, COUNT(*) as count')
            ->first();

        return [
            'average' => round((float) ($result->average ?? 0), 2),
            'count' => (int) ($result->count ?? 0),
        ];
    }
}
