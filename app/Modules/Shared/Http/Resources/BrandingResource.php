<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandingResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->resource['public_id'],
            'site_name' => $this->resource['site_name'],
            'tagline' => $this->resource['tagline'],
            'address_line' => $this->resource['address_line'],
            'support_email' => $this->resource['support_email'],
            'support_phone' => $this->resource['support_phone'],
            'whatsapp_number' => $this->resource['whatsapp_number'],
            'social' => $this->resource['social'],
            'assets' => $this->resource['assets'],
            'updated_at' => $this->resource['updated_at'],
        ];
    }
}
