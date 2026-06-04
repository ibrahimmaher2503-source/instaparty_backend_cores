<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Resources;

use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer in-app inbox row (F17). Exposes only the rendered message —
 * never provider internals, error traces, or template plumbing.
 *
 * @mixin NotificationDispatch
 */
class CustomerNotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $context = (array) ($this->context ?? []);

        return [
            'public_id' => $this->public_id,
            'title' => $context['subject'] ?? null,
            'body' => $context['body'] ?? null,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id !== null ? (string) $this->reference_id : null,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
