<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reviews\Database\Factories\VendorReviewFactory;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewLocale;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorReview extends Model
{
    /** @use HasFactory<VendorReviewFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'vendor_reviews';

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'booking_vendor_id',
        'user_id',
        'rating',
        'body',
        'locale',
        'moderation_status',
        'moderated_by',
        'moderated_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'locale' => ReviewLocale::class,
            'moderation_status' => ModerationStatus::class,
            'moderated_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function newFactory(): VendorReviewFactory
    {
        return VendorReviewFactory::new();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('moderation_status', ModerationStatus::Approved->value);
    }

    public function scopePending($query)
    {
        return $query->where('moderation_status', ModerationStatus::Pending->value);
    }
}
