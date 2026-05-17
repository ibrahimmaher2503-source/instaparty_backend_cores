<?php

declare(strict_types=1);

namespace App\Modules\Geography\Http\Resources;

use App\Modules\Geography\Domain\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin City
 */
class CityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'governorate_public_id' => $this->governorate?->public_id,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
        ];
    }
}
