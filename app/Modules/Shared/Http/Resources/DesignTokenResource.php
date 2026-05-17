<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignTokenResource extends JsonResource
{
    /**
     * @param  array{public_id: ?string, name: string, tokens: array, updated_at: ?string}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->resource['public_id'],
            'name' => $this->resource['name'],
            'tokens' => $this->resource['tokens'],
            'updated_at' => $this->resource['updated_at'],
        ];
    }
}
