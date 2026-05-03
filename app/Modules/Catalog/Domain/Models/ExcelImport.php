<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExcelImport extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'product_type',
        'status',
        'original_filename',
        'stored_path',
        'total_rows',
        'imported_rows',
        'error_rows',
    ];

    protected $casts = [
        'product_type' => ProductType::class,
        'status' => 'string',
        'total_rows' => 'integer',
        'imported_rows' => 'integer',
        'error_rows' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Cross-module reference — acceptable for FK resolution; never call this from Catalog Actions.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ExcelImportError::class);
    }
}
