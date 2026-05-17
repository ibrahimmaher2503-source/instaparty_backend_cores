<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\CapturedState;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use Carbon\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CapturePaymentAction
{
    public function __construct(
        private readonly EloquentPaymentRepository $payments,
        private readonly LedgerWriter $ledgerWriter,
    ) {}

    public function execute(int $paymentId, ?Carbon $capturedAt = null, ?string $idempotencyKey = null): void
    {
        $idempotencyKey ??= "capture:{$paymentId}";

        DB::transaction(function () use ($paymentId, $capturedAt, $idempotencyKey): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);

            // Already captured — idempotent return
            if ($payment->status instanceof CapturedState) {
                return;
            }

            $at = $capturedAt ?? now();

            $correlationId = (string) (Context::get('correlation_id') ?? Str::ulid());

            // Post the payment_capture ledger group (gateway_in_transit → platform_clearing)
            $result = $this->ledgerWriter->post(new PostLedgerTransactionInput(
                kind: TransactionKind::PaymentCapture,
                currency: (string) $payment->amount_currency,
                idempotencyKey: $idempotencyKey,
                correlationId: $correlationId,
                causationId: null,
                initiatorType: 'webhook',
                initiatedByUserId: null,
                entries: [
                    new LedgerEntryInput(
                        walletOwnerType: 'platform_account',
                        walletOwnerId: SuspenseAccount::GatewayInTransit->value,
                        direction: LedgerDirection::Debit,
                        amountMinor: (int) $payment->amount_minor,
                        entryType: LedgerEntryType::PaymentCapture,
                        counterAccountType: 'platform_account',
                        counterAccountId: SuspenseAccount::PlatformClearing->value,
                        relatedEntityType: 'payment',
                        relatedEntityId: $payment->id,
                    ),
                    new LedgerEntryInput(
                        walletOwnerType: 'platform_account',
                        walletOwnerId: SuspenseAccount::PlatformClearing->value,
                        direction: LedgerDirection::Credit,
                        amountMinor: (int) $payment->amount_minor,
                        entryType: LedgerEntryType::PaymentCapture,
                        counterAccountType: 'platform_account',
                        counterAccountId: SuspenseAccount::GatewayInTransit->value,
                        relatedEntityType: 'payment',
                        relatedEntityId: $payment->id,
                    ),
                ],
                descriptionKey: 'settlement.ledger.payment_capture',
                descriptionParams: ['payment_id' => $payment->public_id],
            ));

            $this->payments->markCaptured($payment, $at);

            // Link the capture ledger group to the payment row
            $payment->update([
                'capture_ledger_group_id' => $result->groupId,
                'correlation_id'          => $correlationId,
            ]);

            DB::afterCommit(fn () => event(new PaymentCaptured(
                $payment->id,
                $payment->booking_id,
                (int) $payment->amount_minor,
                (string) $payment->amount_currency,
                Carbon::instance($at),
            )));
        });
    }
}
