<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @response 201 scenario="Vendor profile change request created"
 * {
 *   "data": {
 *     "public_id": "01HWZB3K9XFVTM6P4QD7RNSE2A",
 *     "subject_type": "vendor_profile",
 *     "subject_id_public": "01HWZA1K9XFVTM6P4QD7RNSE2B",
 *     "status": "open",
 *     "cycle_number": 1,
 *     "items": [
 *       {
 *         "public_id": "01HWZB3K9XFVTM6P4QD7RNSE2C",
 *         "field_path": "documents.cr_document",
 *         "requested_change_en": "CR document is blurry — please re-upload a clear scan",
 *         "requested_change_ar": "مستند السجل التجاري غير واضح — يرجى رفع نسخة واضحة",
 *         "item_status": "pending"
 *       }
 *     ],
 *     "created_at": "2026-05-04T10:00:00Z"
 *   },
 *   "meta": {},
 *   "errors": []
 * }
 */
class ChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'subject_type' => $this->subject_type,
            'subject_id_public' => $this->subject_id_public ?? null,
            'status' => $this->status?->value,
            'cycle_number' => $this->cycle_number,
            'items' => ChangeRequestItemResource::collection($this->items),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
