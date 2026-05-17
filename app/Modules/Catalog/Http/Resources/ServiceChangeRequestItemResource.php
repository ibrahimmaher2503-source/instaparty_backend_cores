<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceChangeRequestItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'field_path'           => $this->field_path,
            'field_classification' => $this->field_classification instanceof \BackedEnum
                ? $this->field_classification->value
                : $this->field_classification,
            'before_value'         => $this->before_value,
            'after_value'          => $this->after_value,
        ];
    }
}
