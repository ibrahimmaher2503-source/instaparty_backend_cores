<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @response {
 *   "data": {
 *     "channel": "push",
 *     "event_category": "booking",
 *     "is_enabled": true,
 *     "quiet_hours_start": "22:00",
 *     "quiet_hours_end": "08:00",
 *     "timezone": "Africa/Cairo"
 *   },
 *   "meta": {},
 *   "errors": []
 * }
 * @response scenario="ar" {
 *   "data": {
 *     "channel": "push",
 *     "event_category": "booking",
 *     "is_enabled": true,
 *     "quiet_hours_start": "22:00",
 *     "quiet_hours_end": "08:00",
 *     "timezone": "Africa/Cairo"
 *   },
 *   "meta": {},
 *   "errors": []
 * }
 */
class NotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'channel' => $this->channel->value,
            'event_category' => $this->event_category->value,
            'is_enabled' => $this->is_enabled,
            'quiet_hours_start' => $this->quiet_hours_start,
            'quiet_hours_end' => $this->quiet_hours_end,
            'timezone' => $this->timezone,
        ];
    }
}
