<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\Loyalty\Database\Factories\LoyaltyRedemptionFactory;
use App\Modules\Loyalty\Domain\States\Redemption\RedemptionState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

class LoyaltyRedemption extends Model
{
    use HasFactory, HasStates, HasUlids;

    protected $table = 'loyalty_redemptions';

    protected $fillable = [
        'public_id',
        'customer_id',
        'vendor_profile_id',
        'loyalty_program_id',
        'loyalty_rule_id',
        'booking_id',
        'points_held',
        'discount_minor',
        'discount_currency',
        'status',
        'applied_at',
        'voided_at',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RedemptionState::class,
            'points_held' => 'integer',
            'discount_minor' => 'integer',
            'applied_at' => 'datetime',
            'voided_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function newFactory(): LoyaltyRedemptionFactory
    {
        return LoyaltyRedemptionFactory::new();
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LoyaltyRule::class, 'loyalty_rule_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LoyaltyLedgerEntry::class, 'redemption_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApplied($query)
    {
        return $query->where('status', 'applied');
    }
}
