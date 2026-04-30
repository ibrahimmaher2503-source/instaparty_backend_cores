<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $reference_no
 * @property LifecycleStatus $lifecycle_status
 * @property PaymentStatus $payment_status
 * @property FulfillmentStatus $fulfillment_status
 * @property int $subtotal_minor
 * @property string $subtotal_currency
 * @property int $delivery_total_minor
 * @property string $delivery_total_currency
 * @property int $discount_total_minor
 * @property string $discount_total_currency
 * @property int $total_minor
 * @property string $total_currency
 * @property int $amount_paid_minor
 * @property string $amount_paid_currency
 * @property int|null $guest_count
 * @property string|null $cancelled_by
 * @property \Illuminate\Support\Carbon|null $event_starts_at
 * @property \Illuminate\Support\Carbon|null $event_ends_at
 * @property BookingAddress|null $address
 * @property \Illuminate\Database\Eloquent\Collection<int, BookingVendor> $vendors
 */
class Booking extends Model
{
    use HasFactory;
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'public_id', 'reference_no', 'customer_id', 'occasion_id',
        'lifecycle_status', 'payment_status', 'fulfillment_status',
        'event_starts_at', 'event_ends_at', 'guest_count', 'theme',
        'celebrant_name', 'celebrant_dob', 'celebrant_gender',
        'subtotal_minor', 'subtotal_currency',
        'delivery_total_minor', 'delivery_total_currency',
        'discount_total_minor', 'discount_total_currency',
        'loyalty_redeemed_minor', 'loyalty_redeemed_currency',
        'total_minor', 'total_currency',
        'amount_paid_minor', 'amount_paid_currency',
        'submitted_at', 'confirmed_at', 'cancelled_at', 'cancelled_by',
    ];

    protected $casts = [
        'lifecycle_status' => LifecycleStatus::class,
        'payment_status' => PaymentStatus::class,
        'fulfillment_status' => FulfillmentStatus::class,
        'event_starts_at' => 'datetime',
        'event_ends_at' => 'datetime',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'theme' => 'array',
        'subtotal' => MoneyCast::class . ':subtotal',
        'delivery_total' => MoneyCast::class . ':delivery_total',
        'discount_total' => MoneyCast::class . ':discount_total',
        'loyalty_redeemed' => MoneyCast::class . ':loyalty_redeemed',
        'total' => MoneyCast::class . ':total',
        'amount_paid' => MoneyCast::class . ':amount_paid',
    ];

    public function address(): HasOne
    {
        return $this->hasOne(BookingAddress::class);
    }

    /** @return HasMany<BookingVendor, $this> */
    public function vendors(): HasMany
    {
        return $this->hasMany(BookingVendor::class);
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(BookingItem::class, BookingVendor::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(BookingSnapshot::class);
    }

    public function stateTransitions(): HasMany
    {
        return $this->hasMany(BookingStateTransition::class, 'transitionable_id')
            ->where('transitionable_type', self::class);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('lifecycle_status', LifecycleStatus::Draft);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
