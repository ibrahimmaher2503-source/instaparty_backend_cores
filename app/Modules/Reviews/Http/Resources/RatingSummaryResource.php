<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rating_avg'   => $this->resource['average'],
            'rating_count' => $this->resource['count'],
        ];
    }
}
