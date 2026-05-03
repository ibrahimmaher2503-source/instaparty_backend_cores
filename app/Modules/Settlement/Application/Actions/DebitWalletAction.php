<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Events\WalletDebited;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Support\Facades\DB;

class DebitWalletAction
{
    public function __construct(
        private EloquentWalletRepository $walletRepo,
    ) {}

    /**
     * @param  array<string, mixed>|null  $descriptionParams
     */
    public function execute(
        string $ownerType,
        int $ownerId,
        int $amountMinor,
        string $currency,
        LedgerEntryType $entryType,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?string $descriptionKey = null,
        ?array $descriptionParams = null,
    ): WalletLedgerEntry {
        return DB::transaction(function () use (
            $ownerType,
            $ownerId,
            $amountMinor,
            $currency,
            $entryType,
            $relatedEntityType,
            $relatedEntityId,
            $descriptionKey,
            $descriptionParams,
        ) {
            $wallet = $this->walletRepo->firstOrCreate($ownerType, $ownerId, $currency);

            // Store debits as negative amounts in the ledger
            /** @var WalletLedgerEntry $entry */
            $entry = WalletLedgerEntry::create([
                'wallet_id' => $wallet->id,
                'entry_type' => $entryType,
                'amount_minor' => -abs($amountMinor), // negative for debits
                'currency' => $currency,
                'description_key' => $descriptionKey,
                'description_params' => $descriptionParams,
                'related_entity_type' => $relatedEntityType,
                'related_entity_id' => $relatedEntityId,
            ]);

            $this->walletRepo->decrementBalance($wallet->id, $amountMinor);

            DB::afterCommit(fn () => event(new WalletDebited(
                walletId: $wallet->id,
                amountMinor: $amountMinor,
                currency: $currency,
                entryType: $entryType,
            )));

            return $entry;
        });
    }
}
