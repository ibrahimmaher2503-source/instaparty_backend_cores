<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequestMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceChangeRequest
 *
 * @response {
 *   "data": {
 *     "type": "service_change_request",
 *     "id": "01HTZ8K9XZ...",
 *     "service": {
 *       "id": "01HTZ...",
 *       "name": {"en": "Bouncy Castle XL", "ar": "قلعة نطاطة كبيرة"},
 *       "product_type": "rental"
 *     },
 *     "vendor": {"id": "01HW...", "display_name": {"en": "...", "ar": "..."}},
 *     "status": "pending",
 *     "clarification_round": 0,
 *     "field_count": 4,
 *     "items": [],
 *     "vendor_note": {"en": "...", "ar": "..."},
 *     "admin_note": null,
 *     "messages": [],
 *     "submitted_at": "2026-05-16T11:42:13Z",
 *     "decided_at": null,
 *     "decided_by": null
 *   },
 *   "meta": {"version": 3, "open_lock_held": true},
 *   "errors": []
 * }
 */
class ServiceChangeRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        /** @var ServiceChangeRequest $changeRequest */
        $changeRequest = $this->resource;

        $service = $changeRequest->service;
        $vendorProfile = $changeRequest->vendorProfile;
        $decidedBy = $changeRequest->decidedBy;

        return [
            'type'                => 'service_change_request',
            'id'                  => $this->public_id,
            'service'             => $service !== null ? [
                'id'           => $service->public_id,
                'name'         => $service->getTranslations('name'),
                'product_type' => $service->product_type->value,
            ] : null,
            'vendor'              => $vendorProfile !== null ? [
                'id'           => $vendorProfile->public_id,
                'display_name' => $vendorProfile->getTranslations('business_name'),
            ] : null,
            'status'              => $this->status instanceof \BackedEnum
                ? $this->status->value
                : $this->status,
            'clarification_round' => $this->clarification_round,
            'field_count'         => $this->items->count(),
            'items'               => ServiceChangeRequestItemResource::collection(
                $this->whenLoaded('items')
            ),
            'vendor_note'         => $this->vendor_note !== null
                ? $changeRequest->getTranslations('vendor_note')
                : null,
            'admin_note'          => $this->admin_note !== null
                ? $changeRequest->getTranslations('admin_note')
                : null,
            'messages'            => $this->whenLoaded('messages', function () {
                /** @var \Illuminate\Database\Eloquent\Collection<int, ServiceChangeRequestMessage> $messages */
                $messages = $this->messages;

                return $messages->map(fn (ServiceChangeRequestMessage $message): array => [
                    'author_role' => $message->author_role,
                    'body'        => $message->getTranslations('body'),
                    'created_at'  => $message->created_at?->toIso8601String(),
                ])->values();
            }),
            'submitted_at'        => $this->created_at?->toIso8601String(),
            'decided_at'          => $this->decided_at?->toIso8601String(),
            'decided_by'          => $decidedBy !== null ? [
                'id'   => $decidedBy->public_id,
                'name' => $decidedBy->name,
            ] : null,
            'applied_fields'      => $this->when(
                $this->status === ServiceChangeRequestStatus::Approved
                    && $this->proposed_changes !== null,
                fn (): array => array_keys($this->proposed_changes)
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta'   => [
                'version'        => $this->version,
                'open_lock_held' => $this->status instanceof ServiceChangeRequestStatus
                    && $this->status->isOpen(),
            ],
            'errors' => [],
        ];
    }
}
