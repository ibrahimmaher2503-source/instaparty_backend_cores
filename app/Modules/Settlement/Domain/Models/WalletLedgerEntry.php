<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Settlement\Database\Factories\WalletLedgerEntryFactory;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $wallet_id
 * @property LedgerEntryType $entry_type
 * @property LedgerDirection|null $direction
 * @property int $amount_minor  Unsigned magnitude (Phase 4.9+); signed for legacy rows
 * @property int|null $running_balance_minor
 * @property string $currency
 * @property int|null $transaction_group_id
 * @property string|null $counter_account_type
 * @property int|null $counter_account_id
 * @property string|null $correlation_id
 * @property string|null $causation_id
 * @property string|null $idempotency_key
 * @property string|null $description_key
 * @property array<string, mixed>|null $description_params
 * @property string|null $related_entity_type
 * @property int|null $related_entity_id
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 */
class WalletLedgerEntry extends Model
{
    /** @use HasFactory<WalletLedgerEntryFactory> */
    use HasFactory;

    // Only created_at — no updated_at (append-only)
    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $table = 'wallet_ledger';

    protected $fillable = [
        'wallet_id',
        'entry_type',
        'direction',
        'amount_minor',
        'running_balance_minor',
        'currency',
        'transaction_group_id',
        'counter_account_type',
        'counter_account_id',
        'correlation_id',
        'causation_id',
        'idempotency_key',
        'description_key',
        'description_params',
        'related_entity_type',
        'related_entity_id',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_type'          => LedgerEntryType::class,
            'direction'           => LedgerDirection::class,
            'amount_minor'        => 'integer',
            'running_balance_minor' => 'integer',
            'description_params'  => 'array',
            'posted_at'           => 'datetime',
            'created_at'          => 'datetime',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transactionGroup(): BelongsTo
    {
        return $this->belongsTo(LedgerTransactionGroup::class, 'transaction_group_id');
    }

    /** @return MorphTo<Model, $this> */
    public function related(): MorphTo
    {
        return $this->morphTo('related', 'related_entity_type', 'related_entity_id');
    }

    protected static function newFactory(): WalletLedgerEntryFactory
    {
        return WalletLedgerEntryFactory::new();
    }
}
