<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Database\Factories\NotificationPreferenceFactory;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $table = 'notification_preferences';

    protected $fillable = [
        'public_id',
        'user_id',
        'channel',
        'event_category',
        'is_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
    ];

    protected static function newFactory(): NotificationPreferenceFactory
    {
        return NotificationPreferenceFactory::new();
    }

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'event_category' => EventCategory::class,
            'is_enabled' => 'boolean',
        ];
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByChannelAndCategory(
        Builder $query,
        NotificationChannel $channel,
        EventCategory $category
    ): Builder {
        return $query
            ->where('channel', $channel->value)
            ->where('event_category', $category->value);
    }

    public static function isEnabledFor(
        int $userId,
        NotificationChannel $channel,
        EventCategory $category
    ): bool {
        if ($category === EventCategory::System) {
            return true;
        }

        $pref = static::query()
            ->where('user_id', $userId)
            ->where('channel', $channel->value)
            ->where('event_category', $category->value)
            ->first();

        return $pref === null || $pref->is_enabled;
    }
}
