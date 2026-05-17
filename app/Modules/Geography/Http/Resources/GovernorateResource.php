<?php

declare(strict_types=1);

namespace App\Modules\Geography\Http\Resources;

use App\Modules\Geography\Domain\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Governorate
 */
class GovernorateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'code' => $this->code,
        ];
    }
}
