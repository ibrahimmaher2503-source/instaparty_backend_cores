<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Shared\Domain\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vendor_profile_id
 * @property ProductType $product_type
 * @property Carbon $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property array<string, mixed>|null $revoke_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> active()
 */
class VendorApprovedProductType extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_profile_id',
        'product_type',
        'approved_at',
        'approved_by',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
    ];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
            'revoke_reason' => 'array',
        ];
    }
}
