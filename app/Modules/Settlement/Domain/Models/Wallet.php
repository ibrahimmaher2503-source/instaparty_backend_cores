<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Settlement\Database\Factories\WalletFactory;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $public_id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $currency
 * @property int $balance_minor  Projection cache — written only by ProjectWalletBalanceAction
 * @property int $pending_withdrawal_minor  Projection cache
 * @property int|null $last_ledger_entry_id
 * @property \Illuminate\Support\Carbon|null $last_projected_at
 */
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'public_id',
        'owner_type',
        'owner_id',
        'currency',
        'balance_minor',
        'pending_withdrawal_minor',
        'last_ledger_entry_id',
        'last_projected_at',
    ];

    protected $casts = [
        'balance_minor'             => 'integer',
        'pending_withdrawal_minor'  => 'integer',
        'owner_id'                  => 'integer',
        'last_ledger_entry_id'      => 'integer',
        'last_projected_at'         => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<WalletLedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }

    /** @return HasMany<FinancialSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(FinancialSnapshot::class);
    }

    public function lastLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerEntry::class, 'last_ledger_entry_id');
    }

    protected static function newFactory(): WalletFactory
    {
        return WalletFactory::new();
    }
}
