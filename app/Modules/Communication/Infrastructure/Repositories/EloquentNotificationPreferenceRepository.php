<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Repositories;

use App\Modules\Communication\Application\DTOs\NotificationPreferenceDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EloquentNotificationPreferenceRepository
{
    public function findFor(int $userId, NotificationChannel $channel, EventCategory $category): ?NotificationPreference
    {
        return NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('channel', $channel->value)
            ->where('event_category', $category->value)
            ->first();
    }

    public function upsert(NotificationPreferenceDTO $dto): NotificationPreference
    {
        $pref = NotificationPreference::firstOrNew([
            'user_id' => $dto->userId,
            'channel' => $dto->channel->value,
            'event_category' => $dto->eventCategory->value,
        ]);

        if (! $pref->exists) {
            $pref->public_id = Str::ulid()->toBase32();
        }

        $pref->is_enabled = $dto->isEnabled;
        $pref->quiet_hours_start = $dto->quietHoursStart;
        $pref->quiet_hours_end = $dto->quietHoursEnd;
        $pref->timezone = $dto->timezone;
        $pref->save();

        return $pref;
    }

    public function listForUser(int $userId): Collection
    {
        return NotificationPreference::query()
            ->forUser($userId)
            ->orderBy('channel')
            ->orderBy('event_category')
            ->get();
    }
}
