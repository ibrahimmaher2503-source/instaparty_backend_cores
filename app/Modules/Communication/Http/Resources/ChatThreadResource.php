<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Resources;

use App\Modules\Communication\Domain\Models\ChatThread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatThread
 *
 * @response 200 {
 *   "data": {
 *     "public_id": "01H8XKZJ5G7N6F3MS8AQYQXKZ7",
 *     "status": "locked",
 *     "frozen_at": "2026-05-16T14:33:21+00:00",
 *     "frozen_by": { "id": 42, "name": "Ibrahim" },
 *     "last_message_at": "2026-05-16T14:30:01+00:00",
 *     "open_flags_count": 2
 *   },
 *   "meta": {},
 *   "errors": null
 * }
 *
 * @response 200 scenario="Arabic locale" {
 *   "data": {
 *     "public_id": "01H8XKZJ5G7N6F3MS8AQYQXKZ7",
 *     "status": "locked",
 *     "frozen_at": "2026-05-16T14:33:21+00:00",
 *     "frozen_by": { "id": 42, "name": "إبراهيم" },
 *     "last_message_at": "2026-05-16T14:30:01+00:00",
 *     "open_flags_count": 2
 *   },
 *   "meta": {},
 *   "errors": null
 * }
 */
class ChatThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $frozenByUser = $this->whenLoaded('frozenByUser', fn () => $this->frozenByUser);
        if ($frozenByUser === null && $this->frozen_by !== null) {
            $this->loadMissing('frozenByUser');
            $frozenByUser = $this->frozenByUser;
        }

        return [
            'public_id'        => $this->public_id,
            'status'           => $this->status,
            'frozen_at'        => optional($this->frozen_at)?->toIso8601String(),
            'frozen_by'        => $frozenByUser !== null
                ? ['id' => $frozenByUser->id, 'name' => $frozenByUser->name]
                : null,
            'last_message_at'  => optional($this->updated_at)?->toIso8601String(),
            'open_flags_count' => (int) ($this->unresolved_flags_count ?? $this->unresolvedFlags()->count()),
        ];
    }
}
