<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceFactory;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\States\ServiceStatus\PendingReviewState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\ServiceState;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Shared\Domain\Contracts\ChangeRequestSubject;
use App\Modules\Shared\Domain\Enums\ChangeRequestSubjectType;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\ModelStates\HasStates;
use Spatie\Translatable\HasTranslations;

class Service extends Model implements ChangeRequestSubject, HasMedia
{
    use HasFactory;
    use HasPublicId;
    use HasStates;
    use HasTranslations;
    use InteractsWithMedia;
    use Searchable;
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
        'moderation_notes',
        'moderated_at',
        'moderated_by',
        'base_price_minor',
        'base_price_currency',
        'is_featured',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'short_description', 'long_description', 'moderation_notes'];

    protected $casts = [
        'product_type' => ProductType::class,
        'status' => ServiceState::class,
        'moderated_at' => 'datetime',
        'is_featured' => 'boolean',
        'base_price' => MoneyCast::class.':base_price',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->onlyKeepLatest(11);
    }

    public function getChangeRequestSubjectType(): ChangeRequestSubjectType
    {
        return ChangeRequestSubjectType::Service;
    }

    public function getKey(): int
    {
        return $this->id;
    }

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
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

    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceTheme::class,
            'service_themes_pivot',
            'service_id',
            'service_theme_id',
        )->withPivot('sort_order')->orderBy('sort_order');
    }

    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(ServiceAvailabilityBlock::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'subject_id')
            ->where('subject_type', 'service');
    }

    public function serviceChangeRequests(): HasMany
    {
        return $this->hasMany(ServiceChangeRequest::class);
    }

    public function hasOpenChangeRequest(): bool
    {
        return $this->serviceChangeRequests()
            ->whereIn('status', ['pending', 'awaiting_clarification'])
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereState('status', PublishedState::class);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->whereState('status', PendingReviewState::class);
    }

    public function scopeForType(Builder $query, ProductType $type): Builder
    {
        return $query->where('product_type', $type);
    }

    public function scopeForVendor(Builder $query, int $vendorProfileId): Builder
    {
        return $query->where('vendor_profile_id', $vendorProfileId);
    }

    // -------------------------------------------------------------------------
    // Scout / Meilisearch
    // -------------------------------------------------------------------------

    /**
     * Only published services should be indexed.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status instanceof PublishedState;
    }

    /**
     * Eager-load relationships when building the search index.
     *
     * @return array<int, string>
     */
    public function searchableWith(): array
    {
        return ['category.occasions', 'vendor', 'rentalDetail', 'saleDetail', 'digitalDetail'];
    }

    /**
     * Build the Meilisearch document for this service.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'name_en' => $this->getTranslation('name', 'en'),
            'name_ar' => $this->getTranslation('name', 'ar'),
            'short_description_en' => $this->getTranslation('short_description', 'en'),
            'short_description_ar' => $this->getTranslation('short_description', 'ar'),
            'product_type' => $this->product_type->value,
            'category_id' => $this->category_id,
            'vendor_id' => $this->vendor_profile_id,
            'occasion_ids' => $this->category?->occasions->pluck('id')->toArray() ?? [],
            'price_minor' => $this->base_price_minor,
            'currency' => $this->base_price_currency,
            'status' => $this->status->getValue(),
            'is_active' => $this->status instanceof PublishedState,
            'rating_avg' => (float) ($this->rating_avg ?? 0),
            'vendor_rating' => (float) (optional($this->vendor)->rating_avg ?? 0.0),
            'requires_electricity' => optional($this->rentalDetail)->requires_electricity,
            'requires_outdoor_space' => optional($this->rentalDetail)->requires_outdoor_space,
            'is_perishable' => optional($this->saleDetail)->is_perishable,
            'allows_customization' => optional($this->saleDetail)->allows_customization,
            'delivery_method' => optional($this->digitalDetail)->delivery_method?->value,
            'has_expiry' => optional($this->digitalDetail)->has_expiry,
        ];
    }
}
