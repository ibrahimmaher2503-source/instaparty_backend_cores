<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState as VendorPendingState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\VendorApprovalState;
use App\Modules\Reviews\Domain\Models\VendorReview;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Shared\Domain\Contracts\ChangeRequestSubject;
use App\Modules\Shared\Domain\Enums\ChangeRequestSubjectType;
use Database\Factories\VendorProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\ModelStates\HasStates;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property array<string, string> $business_name
 * @property string $slug
 * @property array<string, string>|null $bio
 * @property string|null $logo_path
 * @property string|null $cover_path
 * @property BusinessType|null $business_type
 * @property string|null $commercial_register_no
 * @property string|null $tax_id
 * @property string|null $national_id
 * @property int $primary_governorate_id
 * @property int $primary_city_id
 * @property array<string, string>|null $address_line
 * @property string|null $latitude
 * @property string|null $longitude
 * @property VendorApprovalState $approval_status
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $rejected_at
 * @property int|null $rejected_by
 * @property Carbon|null $suspended_at
 * @property int|null $suspended_by
 * @property array<string, string>|null $rejection_reason
 * @property string|null $bank_name
 * @property string|null $bank_account_holder
 * @property string|null $bank_iban
 * @property string|null $bank_swift_bic
 * @property string|null $bank_branch
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $user
 * @property-read Collection<int, VendorApprovedProductType> $approvedTypes
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static> approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static> approvedForType(\App\Modules\Catalog\Domain\Enums\ProductType $type)
 */
class VendorProfile extends Model implements ChangeRequestSubject
{
    /** @use HasFactory<VendorProfileFactory> */
    use HasFactory, HasPublicId, HasStates, HasTranslations, SoftDeletes;

    /** @var array<int, string> */
    public $translatable = ['business_name', 'bio', 'address_line', 'rejection_reason', 'suspension_reason'];

    protected $fillable = [
        'public_id',
        'user_id',
        'business_name',
        'slug',
        'bio',
        'logo_path',
        'cover_path',
        'business_type',
        'commercial_register_no',
        'tax_id',
        'national_id',
        'primary_governorate_id',
        'primary_city_id',
        'address_line',
        'latitude',
        'longitude',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'suspended_at',
        'suspended_by',
        'rejection_reason',
        'suspension_reason',
        'bank_name',
        'bank_account_holder',
        'bank_iban',
        'bank_swift_bic',
        'bank_branch',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function newFactory(): VendorProfileFactory
    {
        return VendorProfileFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryGovernorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'primary_governorate_id');
    }

    public function primaryCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'primary_city_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VendorDocument::class);
    }

    public function approvedTypes(): HasMany
    {
        return $this->hasMany(VendorApprovedProductType::class)->whereNull('revoked_at');
    }

    public function approvedProductTypes(): HasMany
    {
        return $this->hasMany(VendorApprovedProductType::class);
    }

    public function businessHours(): HasMany
    {
        return $this->hasMany(VendorBusinessHour::class);
    }

    public function coverageAreas(): HasMany
    {
        return $this->hasMany(VendorCoverageArea::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function bookingVendors(): HasMany
    {
        return $this->hasMany(BookingVendor::class);
    }

    public function wallet(): MorphOne
    {
        return $this->morphOne(Wallet::class, 'owner');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function vendorReviews(): HasMany
    {
        return $this->hasMany(VendorReview::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereState('approval_status', VendorPendingState::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereState('approval_status', ApprovedState::class);
    }

    public function scopeApprovedForType(Builder $query, ProductType $type): Builder
    {
        return $query->whereState('approval_status', ApprovedState::class)->whereHas(
            'approvedTypes',
            fn (Builder $q) => $q->where('product_type', $type->value)
        );
    }

    public function getChangeRequestSubjectType(): ChangeRequestSubjectType
    {
        return ChangeRequestSubjectType::VendorProfile;
    }

    public function getKey(): int
    {
        return $this->id;
    }

    protected function casts(): array
    {
        return [
            'approval_status' => VendorApprovalState::class,
            'business_type' => BusinessType::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
            'response_time_avg_minutes' => 'integer',
        ];
    }
}
