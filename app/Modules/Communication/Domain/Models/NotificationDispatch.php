<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Database\Factories\NotificationDispatchFactory;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationDispatch extends Model
{
    use HasFactory;

    protected $table = 'notification_dispatches';

    public $timestamps = true;

    protected static function newFactory(): NotificationDispatchFactory
    {
        return NotificationDispatchFactory::new();
    }

    protected $fillable = [
        'public_id',
        'notification_template_id',
        'user_id',
        'channel',
        'locale',
        'status',
        'context',
        'provider',
        'provider_ref',
        'reference_type',
        'reference_id',
        'error_message',
        'sent_at',
        'delivered_at',
        // New columns
        'provider_name',
        'provider_message_id',
        'provider_status',
        'provider_error_code',
        'provider_error_message',
        'attempt_count',
        'last_attempt_at',
        'next_retry_at',
        'is_test',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => DispatchStatus::class,
            'context' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'is_test' => 'boolean',
            'attempt_count' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    public function scopeWithStatus(Builder $query, DispatchStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeForChannel(Builder $query, NotificationChannel $channel): Builder
    {
        return $query->where('channel', $channel->value);
    }

    public function scopeInLast24h(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subHours(24));
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query
            ->where('status', DispatchStatus::Failed->value)
            ->where('attempt_count', '<', 5)
            ->where('next_retry_at', '<=', now());
    }

    public function getIsRetryableAttribute(): bool
    {
        return $this->status === DispatchStatus::Failed && $this->attempt_count < 5;
    }
}
