<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Domain\States\DigitalItemStatus\DigitalItemStatus;
use App\Modules\Booking\Domain\States\RentalItemStatus\RentalItemStatus;
use App\Modules\Booking\Domain\States\SaleItemStatus\SaleItemStatus;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\State;

/**
 * @property ProductType $product_type
 * @property array<string,string> $name_snapshot
 * @property int $unit_price_minor
 * @property string $unit_price_currency
 * @property int $line_total_minor
 * @property string $line_total_currency
 * @property int $commission_minor
 * @property string $commission_currency
 * @property string $item_status
 * @property int $quantity
 * @property int $commission_bps
 * @property int $booking_vendor_id
 */
class BookingItem extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id', 'booking_vendor_id', 'service_id', 'product_type',
        'name_snapshot',
        'unit_price_minor', 'unit_price_currency',
        'line_total_minor', 'line_total_currency',
        'commission_minor', 'commission_currency',
        'quantity', 'effective_starts_at', 'effective_ends_at',
        'has_item_slot_override', 'customization_data', 'type_snapshot',
        'fulfillment_data', 'item_status', 'commission_bps',
    ];

    protected $casts = [
        'product_type' => ProductType::class,
        'name_snapshot' => 'array',
        'type_snapshot' => 'array',
        'customization_data' => 'array',
        'fulfillment_data' => 'array',
        'effective_starts_at' => 'datetime',
        'effective_ends_at' => 'datetime',
        'has_item_slot_override' => 'boolean',
        'quantity' => 'integer',
        'commission_bps' => 'integer',
    ];

    public function bookingVendor(): BelongsTo
    {
        return $this->belongsTo(BookingVendor::class);
    }

    public function resolveItemState(): State
    {
        return match ($this->product_type) {
            ProductType::Rental => app(RentalItemStatus::class, ['model' => $this]),
            ProductType::Sale => app(SaleItemStatus::class, ['model' => $this]),
            ProductType::Digital => app(DigitalItemStatus::class, ['model' => $this]),
        };
    }
}
