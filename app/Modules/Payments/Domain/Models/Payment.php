<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use App\Modules\Payments\Database\Factories\PaymentFactory;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'public_id', 'booking_id', 'user_id', 'gateway', 'gateway_ref', 'amount_minor', 'amount_currency',
        'method', 'status', 'captured_at', 'failure_code', 'failure_message', 'metadata',
    ];

    public array $translatable = ['failure_message'];

    protected $casts = [
        'amount' => MoneyCast::class.':amount',
        'method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
        'captured_at' => 'datetime',
        'failure_message' => 'array',
        'metadata' => 'array',
    ];

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Booking\Domain\Models\Booking::class, 'booking_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Pending->value);
    }

    public function scopeCaptured(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Captured->value);
    }

    public function scopeForBooking(Builder $query, int $bookingId): Builder
    {
        return $query->where('booking_id', $bookingId);
    }

    public function scopeStaleHold(Builder $query): Builder
    {
        return $query->pending()->where('created_at', '<', now()->subHours(24));
    }
}
