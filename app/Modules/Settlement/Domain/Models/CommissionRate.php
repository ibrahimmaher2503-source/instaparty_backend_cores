<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Settlement\Database\Factories\CommissionRateFactory;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRate extends Model
{
    /** @use HasFactory<CommissionRateFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'public_id',
        'category_id',
        'product_type',
        'commission_bps',
        'effective_from',
    ];

    protected $casts = [
        'product_type' => ProductType::class,
        'commission_bps' => 'integer',
        'effective_from' => 'date',
        'category_id' => 'integer',
    ];

    /**
     * Category reference from Catalog module.
     * Direct access is acceptable per ADR-0009 §6.3 — categories are reference data.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function newFactory(): CommissionRateFactory
    {
        return CommissionRateFactory::new();
    }
}
