<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Resources;

use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatModerationFlag
 *
 * @response {
 *   "data": {
 *     "public_id": "01HXXXXXXXXXXXXXXXXXXXXXXX",
 *     "flag_type": "phone",
 *     "matched_pattern": "+201001234567",
 *     "action_taken": "redact",
 *     "reviewed_at": "2026-05-16T12:34:56Z",
 *     "reviewed_by": 42,
 *     "message_log_public_id": "01HYYYYYYYYYYYYYYYYYYYYYYY",
 *     "note_en": "Confirmed phone number — message redacted.",
 *     "note_ar": "تم تأكيد وجود رقم هاتف — تم إخفاء الرسالة."
 *   },
 *   "meta": {},
 *   "errors": null
 * }
 */
class ChatModerationFlagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id'             => $this->public_id,
            'flag_type'             => $this->flag_type?->value,
            'matched_pattern'       => $this->matched_pattern,
            'action_taken'          => $this->action_taken?->value,
            'reviewed_at'           => optional($this->reviewed_at)->toIso8601String(),
            'reviewed_by'           => $this->reviewed_by,
            'message_log_public_id' => $this->messageLog?->public_id,
        ];
    }
}
