<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\SettlementRunResult;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SettlementRunAction
{
    public function __construct(
        private readonly LedgerWriter $ledgerWriter,
    ) {}

    public function execute(): SettlementRunResult
    {
        $withdrawals = Withdrawal::query()
            ->where('status', 'approved')
            ->get();

        $settled = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($withdrawals as $withdrawal) {
            try {
                $this->settleOne($withdrawal);
                $settled++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "withdrawal:{$withdrawal->id} — {$e->getMessage()}";
                Log::warning('SettlementRunAction: failed to settle withdrawal', [
                    'withdrawal_id'         => $withdrawal->id,
                    'withdrawal_public_id'  => $withdrawal->public_id,
                    'error'                 => $e->getMessage(),
                ]);
            }
        }

        return new SettlementRunResult(
            settled: $settled,
            failed: $failed,
            errors: $errors,
        );
    }

    private function settleOne(Withdrawal $withdrawal): void
    {
        $correlationId = (string) Str::ulid();

        // Post a withdrawal_settle group: platform_withdrawal_payable → gateway_in_transit
        // This is a platform-only movement; the vendor wallet debit occurred at reserve time.
        $result = $this->ledgerWriter->post(new PostLedgerTransactionInput(
            kind: TransactionKind::WithdrawalSettle,
            currency: $withdrawal->requested_amount_currency,
            idempotencyKey: "wd_settle_batch:{$withdrawal->id}",
            correlationId: $correlationId,
            causationId: null,
            initiatorType: 'scheduler',
            initiatedByUserId: null,
            entries: [
                new LedgerEntryInput(
                    walletOwnerType: 'platform_account',
                    walletOwnerId: SuspenseAccount::PlatformWithdrawalPayable->value,
                    direction: LedgerDirection::Debit,
                    amountMinor: (int) $withdrawal->requested_amount_minor,
                    entryType: LedgerEntryType::WithdrawalSettle,
                    counterAccountType: 'platform_account',
                    counterAccountId: SuspenseAccount::GatewayInTransit->value,
                    relatedEntityType: 'withdrawal',
                    relatedEntityId: $withdrawal->id,
                ),
                new LedgerEntryInput(
                    walletOwnerType: 'platform_account',
                    walletOwnerId: SuspenseAccount::GatewayInTransit->value,
                    direction: LedgerDirection::Credit,
                    amountMinor: (int) $withdrawal->requested_amount_minor,
                    entryType: LedgerEntryType::WithdrawalSettle,
                    counterAccountType: 'platform_account',
                    counterAccountId: SuspenseAccount::PlatformWithdrawalPayable->value,
                ),
            ],
            descriptionKey: 'settlement.ledger.withdrawal_settle_batch',
            descriptionParams: ['withdrawal_public_id' => $withdrawal->public_id],
        ));

        // Grab the first entry from the settle group as the settled_ledger_entry_id pointer
        $settleEntryId = DB::table('wallet_ledger')
            ->where('transaction_group_id', $result->groupId)
            ->value('id');

        DB::table('withdrawals')->where('id', $withdrawal->id)->update([
            'status'                  => 'paid',
            'paid_amount_minor'       => $withdrawal->requested_amount_minor,
            'paid_amount_currency'    => $withdrawal->requested_amount_currency,
            'paid_at'                 => now(),
            'settled_ledger_entry_id' => $settleEntryId,
        ]);
    }
}
