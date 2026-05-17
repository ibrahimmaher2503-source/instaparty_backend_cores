<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Domain\Events\RefundFailed;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Infrastructure\Repositories\EloquentRefundRepository;
use App\Modules\Settlement\Application\Actions\ReverseCommissionAction;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Models\Commission;
use Brick\Money\Money;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessRefundAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly EloquentRefundRepository $refunds,
        private readonly LedgerWriter $ledgerWriter,
        private readonly ReverseCommissionAction $reverseCommission,
    ) {}

    public function execute(int $refundId): void
    {
        DB::transaction(function () use ($refundId): void {
            $refund  = Refund::query()->lockForUpdate()->findOrFail($refundId);
            $payment = Payment::query()->findOrFail($refund->payment_id);
            $this->refunds->markProcessing($refund);

            $result = $this->gateway->refund($payment, Money::ofMinor($refund->amount_minor, $refund->amount_currency));

            if ($result->success) {
                $this->refunds->markCompleted($refund, (string) $result->gatewayRef, now());
                $payment->update(['status' => PaymentStatus::Refunded]);

                $correlationId = (string) (Context::get('correlation_id') ?? Str::ulid());

                // Post refund ledger group: debit platform_clearing → credit platform_refund_payable
                $ledgerResult = $this->ledgerWriter->post(new PostLedgerTransactionInput(
                    kind: TransactionKind::Refund,
                    currency: (string) $refund->amount_currency,
                    idempotencyKey: "refund:{$refund->id}",
                    correlationId: $correlationId,
                    causationId: null,
                    initiatorType: 'system',
                    initiatedByUserId: null,
                    entries: [
                        new LedgerEntryInput(
                            walletOwnerType: 'platform_account',
                            walletOwnerId: SuspenseAccount::PlatformClearing->value,
                            direction: LedgerDirection::Debit,
                            amountMinor: (int) $refund->amount_minor,
                            entryType: LedgerEntryType::RefundDebitPlatform,
                            counterAccountType: 'platform_account',
                            counterAccountId: SuspenseAccount::PlatformRefundPayable->value,
                            relatedEntityType: 'refund',
                            relatedEntityId: $refund->id,
                        ),
                        new LedgerEntryInput(
                            walletOwnerType: 'platform_account',
                            walletOwnerId: SuspenseAccount::PlatformRefundPayable->value,
                            direction: LedgerDirection::Credit,
                            amountMinor: (int) $refund->amount_minor,
                            entryType: LedgerEntryType::RefundCreditCustomer,
                            counterAccountType: 'platform_account',
                            counterAccountId: SuspenseAccount::PlatformClearing->value,
                            relatedEntityType: 'refund',
                            relatedEntityId: $refund->id,
                        ),
                    ],
                    descriptionKey: 'settlement.ledger.refund',
                    descriptionParams: ['refund_id' => $refund->public_id],
                ));

                // Link the ledger group to the refund row
                $refund->update(['ledger_group_id' => $ledgerResult->groupId]);

                // Reverse the corresponding commission proportionally
                $commission = Commission::query()
                    ->where('payment_id', $payment->id)
                    ->first();

                if ($commission !== null) {
                    $this->reverseCommission->execute(
                        commission: $commission,
                        refund: new RefundSnapshotDto(
                            id: $refund->id,
                            publicId: (string) $refund->public_id,
                            paymentId: $payment->id,
                            bookingItemId: null,
                            amountMinor: (int) $refund->amount_minor,
                            currency: (string) $refund->amount_currency,
                            completedAt: now(),
                        ),
                        idempotencyKey: "comm_rev:{$commission->id}:{$refund->id}",
                        correlationId: $correlationId,
                        causationId: null,
                    );
                }

                DB::afterCommit(fn () => event(new RefundCompleted(
                    $refund->id,
                    $payment->id,
                    $payment->booking_id,
                    (int) $refund->amount_minor,
                    (string) $refund->amount_currency,
                    $refund->reason_code->value,
                )));

                return;
            }

            $this->refunds->markFailed($refund, (string) $result->failureMessage);
            DB::afterCommit(fn () => event(new RefundFailed(
                $refund->id,
                $payment->id,
                ['en' => (string) $result->failureMessage, 'ar' => (string) $result->failureMessage],
            )));
        });
    }
}
