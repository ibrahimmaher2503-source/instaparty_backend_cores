<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\Loyalty\Database\Factories\LoyaltyProgramFactory;
use App\Modules\Loyalty\Domain\Enums\ProgramStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;

class LoyaltyProgram extends Model
{
    use HasFactory, HasTranslations, HasUlids;

    protected $table = 'loyalty_programs';

    public array $translatable = ['name', 'terms'];

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'name',
        'terms',
        'currency',
        'status',
        'expiration_days',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProgramStatus::class,
            'expiration_days' => 'integer',
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

    public function rules(): HasMany
    {
        return $this->hasMany(LoyaltyRule::class, 'loyalty_program_id');
    }

    public function activeRule(): HasOne
    {
        return $this->hasOne(LoyaltyRule::class, 'loyalty_program_id')->where('is_active', true)->latestOfMany('effective_from');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LoyaltyLedgerEntry::class, 'loyalty_program_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRedemption::class, 'loyalty_program_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', ProgramStatus::Active->value);
    }
}
