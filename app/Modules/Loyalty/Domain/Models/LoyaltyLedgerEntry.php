<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\Loyalty\Database\Factories\LoyaltyLedgerEntryFactory;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use Illuminate\Database\Eloquent\Builder;
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
        'user_id',
        'vendor_profile_id',
        'loyalty_program_id',
        'direction',
        'points',
        'balance_after',
        'reference_type',
        'reference_id',
        'reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => LedgerDirection::class,
            'points' => 'integer',
            'balance_after' => 'integer',
            'reference_id' => 'integer',
            'expires_at' => 'datetime',
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

    public function scopeForUserAndVendor(Builder $query, int $userId, int $vendorProfileId): Builder
    {
        return $query->where('user_id', $userId)->where('vendor_profile_id', $vendorProfileId);
    }
}
