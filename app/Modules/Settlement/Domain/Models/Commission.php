<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Settlement\Database\Factories\CommissionFactory;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $public_id
 * @property int $booking_item_id
 * @property int $payment_id
 * @property int $vendor_profile_id
 * @property int|null $category_id
 * @property ProductType $product_type
 * @property int $gross_amount_minor
 * @property string $gross_amount_currency
 * @property int $commission_bps
 * @property int $commission_minor
 * @property string $commission_currency
 * @property int $vendor_share_minor
 * @property string $vendor_share_currency
 * @property int $reversed_amount_minor
 * @property CommissionStatus $status
 * @property Carbon|null $created_at
 */
class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use HasFactory;

    use HasPublicId;

    // Append-only: only created_at, no updated_at (status field only may be updated)
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'public_id',
        'booking_item_id',
        'payment_id',
        'vendor_profile_id',
        'category_id',
        'product_type',
        'gross_amount_minor',
        'gross_amount_currency',
        'commission_bps',
        'commission_minor',
        'commission_currency',
        'vendor_share_minor',
        'vendor_share_currency',
        'reversed_amount_minor',
        'status',
    ];

    protected $casts = [
        'status' => CommissionStatus::class,
        'product_type' => ProductType::class,
        'commission_bps' => 'integer',
        'gross_amount_minor' => 'integer',
        'commission_minor' => 'integer',
        'vendor_share_minor' => 'integer',
        'reversed_amount_minor' => 'integer',
        'booking_item_id' => 'integer',
        'payment_id' => 'integer',
        'vendor_profile_id' => 'integer',
        'category_id' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function newFactory(): CommissionFactory
    {
        return CommissionFactory::new();
    }
}
