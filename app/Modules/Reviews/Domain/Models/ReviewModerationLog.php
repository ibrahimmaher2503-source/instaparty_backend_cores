<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reviews\Database\Factories\ReviewModerationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ReviewModerationLog extends Model
{
    /** @use HasFactory<ReviewModerationLogFactory> */
    use HasFactory, HasTranslations;

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
            'reason' => 'array',
        ];
    }

    protected static function newFactory(): ReviewModerationLogFactory
    {
        return ReviewModerationLogFactory::new();
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_at ??= now();
        });
    }
}
