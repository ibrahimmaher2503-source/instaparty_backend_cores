<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Database\Factories\ChatMessageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Append-only mirror of a Firestore chat message.
 *
 * Inviolable invariant (FR-EXT-036-015, ADR-0014): once a row is INSERTed,
 * only `flagged`, `flag_reason`, `redacted` may be UPDATEd. Any other dirty
 * column on `updating` throws — see the booted() guard below.
 */
class ChatMessageLog extends Model
{
    use HasFactory;

    protected $table = 'chat_message_log';

    public $timestamps = false;

    protected static function newFactory(): ChatMessageLogFactory
    {
        return ChatMessageLogFactory::new();
    }

    protected $fillable = [
        'flagged',
        'flag_reason',
        'redacted',
    ];

    protected $casts = [
        'flagged' => 'boolean',
        'redacted' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'chat_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Domain\Models\User::class, 'sender_id');
    }

    public function flags(): HasMany
    {
        return $this->hasMany(ChatModerationFlag::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $log): void {
            $forbidden = array_diff(
                array_keys($log->getDirty()),
                ['flagged', 'flag_reason', 'redacted'],
            );

            if (! empty($forbidden)) {
                throw new LogicException(
                    'chat_message_log content fields are append-only — only flagged, flag_reason, redacted may be updated. Attempted to update: '
                    .implode(', ', $forbidden)
                );
            }
        });
    }
}
