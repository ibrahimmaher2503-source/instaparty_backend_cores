<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Application\DTOs\ResolveChargebackDto;
use App\Modules\Payments\Domain\Events\ChargebackResolved;
use App\Modules\Payments\Domain\Models\PaymentChargeback;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveChargebackAction
{
    public function execute(ResolveChargebackDto $dto): PaymentChargeback
    {
        return DB::transaction(function () use ($dto): PaymentChargeback {
            /** @var PaymentChargeback $chargeback */
            $chargeback = PaymentChargeback::query()->lockForUpdate()->findOrFail($dto->chargebackId);

            if ($chargeback->isResolved()) {
                throw ValidationException::withMessages([
                    'status' => __('payments::chargebacks.already_resolved'),
                ]);
            }

            $chargeback->update([
                'status' => $dto->status,
                'resolved_at' => now(),
                'admin_notes' => $dto->adminNotes ?? $chargeback->admin_notes,
                'updated_by' => $dto->adminUserId,
            ]);

            DB::afterCommit(fn () => ChargebackResolved::dispatch(
                $chargeback->id,
                $chargeback->payment_id,
                $dto->status,
                $dto->adminUserId,
            ));

            return $chargeback->fresh();
        });
    }
}
