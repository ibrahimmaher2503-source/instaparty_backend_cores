<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\Loyalty\Database\Factories\LoyaltyProgramFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class LoyaltyProgram extends Model
{
    use HasFactory, HasTranslations, HasUlids, SoftDeletes;

    protected $table = 'loyalty_programs';

    public array $translatable = ['name', 'terms'];

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'is_active',
        'points_per_currency_unit',
        'points_value_minor',
        'points_value_currency',
        'min_points_to_redeem',
        'max_redeem_pct',
        'points_expire_after_days',
        'name',
        'terms',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'points_per_currency_unit' => 'decimal:4',
            'points_value_minor' => 'integer',
            'min_points_to_redeem' => 'integer',
            'max_redeem_pct' => 'integer',
            'points_expire_after_days' => 'integer',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function newFactory(): LoyaltyProgramFactory
    {
        return LoyaltyProgramFactory::new();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Identity\Domain\Models\VendorProfile', 'vendor_profile_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(LoyaltyRule::class, 'loyalty_program_id');
    }

    public function activeRules(): HasMany
    {
        return $this->hasMany(LoyaltyRule::class, 'loyalty_program_id')->where('is_active', true);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LoyaltyLedgerEntry::class, 'loyalty_program_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRedemption::class, 'loyalty_program_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
