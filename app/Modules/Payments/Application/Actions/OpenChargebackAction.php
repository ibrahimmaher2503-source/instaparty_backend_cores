<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Application\DTOs\OpenChargebackDto;
use App\Modules\Payments\Domain\Enums\ChargebackStatus;
use App\Modules\Payments\Domain\Events\ChargebackOpened;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentChargeback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenChargebackAction
{
    public function execute(OpenChargebackDto $dto): PaymentChargeback
    {
        return DB::transaction(function () use ($dto): PaymentChargeback {
            $payment = Payment::query()->lockForUpdate()->findOrFail($dto->paymentId);

            if ($dto->amountMinor > $payment->amount_minor) {
                throw ValidationException::withMessages([
                    'amount_minor' => __('payments::chargebacks.amount_exceeds_payment'),
                ]);
            }

            /** @var PaymentChargeback $chargeback */
            $chargeback = PaymentChargeback::query()->create([
                'public_id' => (string) Str::ulid(),
                'payment_id' => $payment->id,
                'gateway_case_id' => $dto->gatewayCaseId,
                'reason' => $dto->reason,
                'status' => ChargebackStatus::Open,
                'amount_minor' => $dto->amountMinor,
                'amount_currency' => $dto->amountCurrency,
                'opened_at' => now(),
                'admin_notes' => $dto->adminNotes,
                'created_by' => $dto->adminUserId,
            ]);

            DB::afterCommit(fn () => ChargebackOpened::dispatch(
                $chargeback->id,
                $payment->id,
                $dto->amountMinor,
                $dto->amountCurrency,
                $dto->adminUserId,
            ));

            return $chargeback;
        });
    }
}
