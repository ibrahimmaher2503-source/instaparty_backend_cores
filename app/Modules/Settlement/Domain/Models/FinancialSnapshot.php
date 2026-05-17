<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialSnapshot extends Model
{
    public $timestamps = false;

    protected $table = 'financial_snapshots';

    protected $fillable = [
        'wallet_id',
        'snapshot_at',
        'as_of_ledger_entry_id',
        'available_minor',
        'pending_minor',
        'currency',
        'checksum',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function asOfEntry(): BelongsTo
    {
        return $this->belongsTo(WalletLedgerEntry::class, 'as_of_ledger_entry_id');
    }
}
