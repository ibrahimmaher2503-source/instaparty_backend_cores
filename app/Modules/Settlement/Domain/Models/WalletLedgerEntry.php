<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use App\Modules\Settlement\Database\Factories\WalletLedgerEntryFactory;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $wallet_id
 * @property LedgerEntryType $entry_type
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $description_key
 * @property array<string, mixed>|null $description_params
 * @property string|null $related_entity_type
 * @property int|null $related_entity_id
 * @property \Illuminate\Support\Carbon|null $created_at
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
        'amount_minor',
        'currency',
        'description_key',
        'description_params',
        'related_entity_type',
        'related_entity_id',
    ];

    protected $casts = [
        'entry_type' => LedgerEntryType::class,
        'amount_minor' => 'integer',
        'description_params' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return MorphTo<Model, $this> */
    public function related(): MorphTo
    {
        return $this->morphTo('related');
    }

    protected static function newFactory(): WalletLedgerEntryFactory
    {
        return WalletLedgerEntryFactory::new();
    }
}
