<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property VendorSubStatus $sub_status
 * @property int $subtotal_minor
 * @property string $subtotal_currency
 * @property int $delivery_fee_minor
 * @property string $delivery_fee_currency
 * @property int $commission_minor
 * @property string $commission_currency
 * @property int $vendor_payout_minor
 * @property string $vendor_payout_currency
 * @property int $vendor_profile_id
 */
class BookingVendor extends Model
{
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
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }
}
