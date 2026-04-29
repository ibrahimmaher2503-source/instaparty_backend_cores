<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var VendorDocument $doc */
        $doc = $this->resource;

        return [
            'id' => $doc->public_id,
            'doc_type' => $doc->doc_type->value,
            'file_name' => $doc->file_name,
            'status' => $doc->status->value,
            'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
            'created_at' => $doc->created_at->toIso8601String(),
        ];
    }
}
