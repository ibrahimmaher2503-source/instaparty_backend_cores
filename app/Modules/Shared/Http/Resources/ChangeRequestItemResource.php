<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangeRequestItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'field_path' => $this->field_path,
            'requested_change_en' => $this->requested_change_en,
            'requested_change_ar' => $this->requested_change_ar,
            'item_status' => $this->item_status?->value,
        ];
    }
}
