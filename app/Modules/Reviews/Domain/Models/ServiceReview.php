<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Models;

use App\Modules\Reviews\Database\Factories\ServiceReviewFactory;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewLocale;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceReview extends Model
{
    /** @use HasFactory<ServiceReviewFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'service_reviews';

    protected $fillable = [
        'public_id',
        'service_id',
        'booking_item_id',
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

    protected static function newFactory(): ServiceReviewFactory
    {
        return ServiceReviewFactory::new();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Catalog\Domain\Models\Service');
    }

    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Booking\Domain\Models\BookingItem');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Identity\Domain\Models\User', 'user_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Identity\Domain\Models\User', 'moderated_by');
    }

    public function vendorResponse(): HasOne
    {
        return $this->hasOne(ReviewResponse::class, 'review_id')
            ->where('review_type', ReviewType::Service->value)
            ->latest('id');
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
