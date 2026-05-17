<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\LedgerTransactionResult;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Events\WalletCredited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditWalletAction
{
    public function __construct(
        private readonly LedgerWriter $ledgerWriter,
    ) {}

    public function execute(
        int $walletId,
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        string $correlationId,
        ?string $causationId = null,
        LedgerEntryType $entryType = LedgerEntryType::ManualAdjustment,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?string $walletOwnerType = null,
        ?int $walletOwnerId = null,
    ): LedgerTransactionResult {
        // Resolve the wallet owner from the wallet id if not provided
        if ($walletOwnerType === null || $walletOwnerId === null) {
            $row = DB::table('wallets')->where('id', $walletId)->first(['owner_type', 'owner_id']);
            $walletOwnerType = $row->owner_type;
            $walletOwnerId   = (int) $row->owner_id;
        }

        $result = $this->ledgerWriter->post(new PostLedgerTransactionInput(
            kind: TransactionKind::ManualAdjustment,
            currency: $currency,
            idempotencyKey: $idempotencyKey,
            correlationId: $correlationId,
            causationId: $causationId ?? (string) Str::ulid(),
            initiatorType: 'system',
            initiatedByUserId: null,
            entries: [
                new LedgerEntryInput(
                    walletOwnerType: $walletOwnerType,
                    walletOwnerId: $walletOwnerId,
                    direction: LedgerDirection::Credit,
                    amountMinor: $amountMinor,
                    entryType: $entryType,
                    counterAccountType: 'platform_account',
                    counterAccountId: SuspenseAccount::PlatformAdjustments->value,
                    descriptionKey: 'settlement.credit',
                    relatedEntityType: $relatedEntityType,
                    relatedEntityId: $relatedEntityId,
                ),
                new LedgerEntryInput(
                    walletOwnerType: 'platform_account',
                    walletOwnerId: SuspenseAccount::PlatformAdjustments->value,
                    direction: LedgerDirection::Debit,
                    amountMinor: $amountMinor,
                    entryType: $entryType,
                    counterAccountType: $walletOwnerType,
                    counterAccountId: $walletOwnerId,
                    descriptionKey: 'settlement.platform_debit',
                ),
            ],
            descriptionKey: 'settlement.credit_wallet',
            metadata: [],
        ));

        if (! $result->wasIdempotentReplay) {
            DB::afterCommit(fn () => event(new WalletCredited(
                walletId: $walletId,
                amountMinor: $amountMinor,
                currency: $currency,
                entryType: $entryType,
            )));
        }

        return $result;
    }
}
