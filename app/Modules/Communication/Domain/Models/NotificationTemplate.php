<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class NotificationTemplate extends Model
{
    use HasTranslations;

    protected $table = 'notification_templates';

    /** @var array<int, string> */
    public array $translatable = ['body', 'subject'];

    protected $fillable = [
        'public_id',
        'event_key',
        'channel',
        'audience',
        'body',
        'subject',
        'variables',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'audience' => NotificationAudience::class,
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForEventChannelAudience(
        Builder $query,
        string $eventKey,
        NotificationChannel $channel,
        NotificationAudience $audience
    ): Builder {
        return $query
            ->where('event_key', $eventKey)
            ->where('channel', $channel->value)
            ->where('audience', $audience->value);
    }
}
