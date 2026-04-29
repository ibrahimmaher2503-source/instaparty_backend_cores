<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceFactory;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Service extends Model implements HasMedia
{
    use HasFactory;
    use HasPublicId;
    use HasTranslations;
    use InteractsWithMedia;
    use SoftDeletes;

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'category_id',
        'product_type',
        'name',
        'short_description',
        'long_description',
        'slug',
        'status',
        'base_price_minor',
        'base_price_currency',
        'is_featured',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'short_description', 'long_description'];

    protected $casts = [
        'product_type'  => ProductType::class,
        'status'        => ServiceStatus::class,
        'is_featured'   => 'boolean',
        'base_price'    => MoneyCast::class . ':base_price',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->onlyKeepLatest(11);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Cross-module reference — acceptable for FK resolution; never call this from Catalog Actions.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Domain\Models\VendorProfile::class, 'vendor_profile_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function rentalDetail(): HasOne
    {
        return $this->hasOne(ServiceRentalDetail::class);
    }

    public function saleDetail(): HasOne
    {
        return $this->hasOne(ServiceSaleDetail::class);
    }

    public function digitalDetail(): HasOne
    {
        return $this->hasOne(ServiceDigitalDetail::class);
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(ServiceInventoryReservation::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ServiceStatus::Published);
    }

    public function scopeForType(Builder $query, ProductType $type): Builder
    {
        return $query->where('product_type', $type);
    }

    public function scopeForVendor(Builder $query, int $vendorProfileId): Builder
    {
        return $query->where('vendor_profile_id', $vendorProfileId);
    }
}
