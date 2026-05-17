<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $booking_id
 * @property Booking|null $booking
 * @property int $vendor_profile_id
 * @property VendorSubStatus $sub_status
 * @property Carbon|null $response_deadline
 * @property Carbon|null $responded_at
 * @property int $subtotal_minor
 * @property string $subtotal_currency
 * @property int $delivery_fee_minor
 * @property string $delivery_fee_currency
 * @property int $commission_minor
 * @property string $commission_currency
 * @property int $vendor_payout_minor
 * @property string $vendor_payout_currency
 * @property array<string,string>|null $rejection_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Collection<int, BookingItem> $items
 */
class BookingVendor extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'public_id', 'booking_id', 'vendor_profile_id',
        'sub_status', 'response_deadline', 'responded_at',
        'rejection_reason', 'vendor_notes',
        'subtotal_minor', 'subtotal_currency',
        'delivery_fee_minor', 'delivery_fee_currency',
        'commission_minor', 'commission_currency',
        'vendor_payout_minor', 'vendor_payout_currency',
    ];

    protected $casts = [
        'sub_status' => VendorSubStatus::class,
        'response_deadline' => 'datetime',
        'responded_at' => 'datetime',
        'rejection_reason' => 'array',
        'vendor_notes' => 'array',
        'subtotal' => MoneyCast::class.':subtotal',
        'delivery_fee' => MoneyCast::class.':delivery_fee',
        'commission' => MoneyCast::class.':commission',
        'vendor_payout' => MoneyCast::class.':vendor_payout',
    ];

    protected static function newFactory(): BookingVendorFactory
    {
        return BookingVendorFactory::new();
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    /** @return HasMany<BookingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /** @return HasMany<BookingModification, $this> */
    public function modifications(): HasMany
    {
        return $this->hasMany(BookingModification::class);
    }
}
