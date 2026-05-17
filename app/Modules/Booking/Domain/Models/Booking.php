<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Database\Factories\BookingFactory;
use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\BookingLifecycleState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState as LifecycleDraftState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\BookingPaymentState;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Database\Eloquent\Builder;
use Spatie\ModelStates\HasStates;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $reference_no
 * @property BookingLifecycleState $lifecycle_status
 * @property BookingPaymentState $payment_status
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
 * @property Carbon|null $event_starts_at
 * @property Carbon|null $event_ends_at
 * @property BookingAddress|null $address
 * @property Collection<int, BookingVendor> $vendors
 * @property Collection<int, Payment> $payments
 */
class Booking extends Model
{
    use HasFactory;
    use HasPublicId;
    use HasStates;
    use SoftDeletes;

    protected $fillable = [
        'public_id', 'reference_no', 'customer_id', 'occasion_id',
        'lifecycle_status', 'payment_status', 'fulfillment_status',
        'event_starts_at', 'event_ends_at', 'guest_count', 'theme',
        'celebrant_name', 'celebrant_dob', 'celebrant_gender',
        'subtotal_minor', 'subtotal_currency',
        'delivery_total_minor', 'delivery_total_currency',
        'discount_total_minor', 'discount_total_currency',
        'loyalty_redeemed_minor', 'loyalty_redeemed_currency', 'loyalty_redemption_public_id',
        'total_minor', 'total_currency', 'total_vat_minor',
        'amount_paid_minor', 'amount_paid_currency',
        'submitted_at', 'confirmed_at', 'cancelled_at', 'cancelled_by',
        'payment_hold_expires_at',
    ];

    protected $casts = [
        'lifecycle_status' => BookingLifecycleState::class,
        'payment_status' => BookingPaymentState::class,
        'fulfillment_status' => FulfillmentStatus::class,
        'event_starts_at' => 'datetime',
        'event_ends_at' => 'datetime',
        'payment_hold_expires_at' => 'datetime',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'theme' => 'array',
        'subtotal' => MoneyCast::class.':subtotal',
        'delivery_total' => MoneyCast::class.':delivery_total',
        'discount_total' => MoneyCast::class.':discount_total',
        'loyalty_redeemed' => MoneyCast::class.':loyalty_redeemed',
        'total' => MoneyCast::class.':total',
        'amount_paid' => MoneyCast::class.':amount_paid',
    ];

    protected static function newFactory(): BookingFactory
    {
        return BookingFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function occasion(): BelongsTo
    {
        return $this->belongsTo(Occasion::class, 'occasion_id');
    }

    public function address(): HasOne
    {
        return $this->hasOne(BookingAddress::class);
    }

    /** @return HasMany<BookingVendor, $this> */
    public function vendors(): HasMany
    {
        return $this->hasMany(BookingVendor::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
        return $this->hasMany(StateTransition::class, 'transitionable_id')
            ->where('transitionable_type', self::class);
    }

    /** @return HasMany<BookingCustomerNote, $this> */
    public function customerNotes(): HasMany
    {
        return $this->hasMany(BookingCustomerNote::class)->latest('created_at');
    }

    /** @return HasMany<BookingAdminIntervention, $this> */
    public function adminInterventions(): HasMany
    {
        return $this->hasMany(BookingAdminIntervention::class)->latest();
    }

    /**
     * Shortcut to the booking modifications that are still pending customer decision.
     * Modifications are stored on booking_vendors (via booking_modifications table),
     * so this traverses through the vendors relationship.
     *
     * @return HasManyThrough<BookingModification, $this>
     */
    public function pendingModifications(): HasManyThrough
    {
        return $this->hasManyThrough(
            BookingModification::class,
            BookingVendor::class,
            'booking_id',
            'booking_vendor_id',
        )->where('booking_modifications.status', 'pending');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->whereState('lifecycle_status', LifecycleDraftState::class);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
