<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\Loyalty\Database\Factories\LoyaltyLedgerEntryFactory;
use App\Modules\Loyalty\Domain\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;
use Spatie\Translatable\HasTranslations;

class LoyaltyLedgerEntry extends Model
{
    use HasFactory, HasTranslations, HasUlids;

    // Append-only: no updated_at
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $table = 'loyalty_ledger';

    public array $translatable = ['reason'];

    protected $fillable = [
        'public_id',
        'customer_id',
        'vendor_profile_id',
        'loyalty_program_id',
        'entry_type',
        'points',
        'booking_id',
        'booking_item_id',
        'redemption_id',
        'reversed_from_ledger_id',
        'product_type',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => LedgerEntryType::class,
            'points' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function newFactory(): LoyaltyLedgerEntryFactory
    {
        return LoyaltyLedgerEntryFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('LoyaltyLedgerEntry is append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('LoyaltyLedgerEntry is append-only and cannot be deleted.');
        });
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function redemption(): BelongsTo
    {
        return $this->belongsTo(LoyaltyRedemption::class, 'redemption_id');
    }

    public function reversedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_from_ledger_id');
    }

    public function scopeForCustomerAndVendor($query, int $customerId, int $vendorProfileId)
    {
        return $query->where('customer_id', $customerId)->where('vendor_profile_id', $vendorProfileId);
    }
}
