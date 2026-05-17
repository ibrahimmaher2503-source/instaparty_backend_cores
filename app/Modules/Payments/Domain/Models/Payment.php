<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Payments\Database\Factories\PaymentFactory;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\States\PaymentStatus\CapturedState;
use App\Modules\Payments\Domain\States\PaymentStatus\PaymentState;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

class Payment extends Model
{
    use HasFactory;
    use HasPublicId;
    use HasStates;

    protected $fillable = [
        'public_id', 'booking_id', 'user_id', 'gateway', 'gateway_ref', 'amount_minor', 'amount_currency',
        'method', 'captured_at', 'failure_code', 'failure_message', 'metadata',
        // Phase 4.9
        'correlation_id', 'capture_ledger_group_id',
    ];

    public array $translatable = ['failure_message'];

    protected $casts = [
        'amount' => MoneyCast::class.':amount',
        'method' => PaymentMethod::class,
        'status' => PaymentState::class,
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
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereState('status', PendingState::class);
    }

    public function scopeCaptured(Builder $query): Builder
    {
        return $query->whereState('status', CapturedState::class);
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
