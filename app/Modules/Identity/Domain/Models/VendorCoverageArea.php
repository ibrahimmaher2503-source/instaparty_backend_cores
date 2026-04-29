<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Shared\Domain\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vendor_profile_id
 * @property int $city_id
 * @property int $delivery_fee_minor
 * @property string $delivery_fee_currency
 * @property int $min_order_minor
 * @property string $min_order_currency
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VendorCoverageArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_profile_id',
        'city_id',
        'delivery_fee_minor',
        'delivery_fee_currency',
        'min_order_minor',
        'min_order_currency',
    ];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    protected function casts(): array
    {
        return [
            'delivery_fee' => MoneyCast::class.':delivery_fee',
            'min_order' => MoneyCast::class.':min_order',
            'delivery_fee_minor' => 'integer',
            'min_order_minor' => 'integer',
        ];
    }
}
