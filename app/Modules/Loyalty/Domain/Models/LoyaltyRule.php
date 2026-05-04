<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\Loyalty\Database\Factories\LoyaltyRuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class LoyaltyRule extends Model
{
    use HasFactory, HasTranslations, HasUlids;

    protected $table = 'loyalty_rules';

    public array $translatable = ['label'];

    protected $fillable = [
        'public_id',
        'loyalty_program_id',
        'label',
        'earn_points_per_minor',
        'earn_minor_per_unit',
        'redemption_ratio_points',
        'redemption_ratio_minor',
        'min_points_to_redeem',
        'max_redeem_pct_bps',
        'is_active',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'earn_points_per_minor' => 'integer',
            'earn_minor_per_unit' => 'integer',
            'redemption_ratio_points' => 'integer',
            'redemption_ratio_minor' => 'integer',
            'min_points_to_redeem' => 'integer',
            'max_redeem_pct_bps' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function newFactory(): LoyaltyRuleFactory
    {
        return LoyaltyRuleFactory::new();
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function computeEarnPoints(int $netMinorAmount): int
    {
        return (int) floor($netMinorAmount * $this->earn_points_per_minor / $this->earn_minor_per_unit);
    }

    public function computeDiscountMinor(int $points): int
    {
        return (int) floor($points * $this->redemption_ratio_minor / $this->redemption_ratio_points);
    }
}
