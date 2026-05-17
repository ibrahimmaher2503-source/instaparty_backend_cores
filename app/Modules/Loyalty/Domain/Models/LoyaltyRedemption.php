<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\Loyalty\Database\Factories\LoyaltyRedemptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyRedemption extends Model
{
    use HasFactory, HasUlids;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $table = 'loyalty_redemptions';

    protected $fillable = [
        'public_id',
        'user_id',
        'vendor_profile_id',
        'loyalty_program_id',
        'booking_id',
        'points_redeemed',
        'amount_minor',
        'amount_currency',
    ];

    protected function casts(): array
    {
        return [
            'points_redeemed' => 'integer',
            'amount_minor' => 'integer',
            'created_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Identity\Domain\Models\User');
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Identity\Domain\Models\VendorProfile');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Booking\Domain\Models\Booking');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LoyaltyLedgerEntry::class, 'reference_id')
            ->where('reference_type', 'loyalty_redemption');
    }
}
