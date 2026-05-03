<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ReviewModerationLog extends Model
{
    use HasTranslations;

    protected $table = 'review_moderation_log';

    public $timestamps = false;

    public $translatable = ['reason'];

    protected $fillable = [
        'review_type',
        'review_id',
        'from_status',
        'to_status',
        'moderator_id',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'reason'     => 'array',
        ];
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Domain\Models\User::class, 'moderator_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_at ??= now();
        });
    }
}
