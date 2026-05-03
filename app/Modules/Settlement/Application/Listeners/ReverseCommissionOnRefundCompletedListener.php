<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Listeners;

use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Settlement\Application\Actions\ReverseCommissionAction;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentCommissionRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ReverseCommissionOnRefundCompletedListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private SettlementPaymentReader $paymentReader,
        private EloquentCommissionRepository $commissionRepo,
        private ReverseCommissionAction $reverseCommission,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(RefundCompleted $event): void
    {
        $refund = $this->paymentReader->findRefundById($event->refundId);

        if ($refund === null) {
            Log::error('Settlement: RefundCompleted received but refund not found', [
                'refund_id' => $event->refundId,
            ]);

            return;
        }

        if ($refund->bookingItemId !== null) {
            // Item-level refund — reverse only the matching commission
            $commission = $this->commissionRepo->findByBookingItemId($refund->bookingItemId);

            if ($commission === null) {
                Log::warning('Settlement: no commission found for booking item on refund', [
                    'refund_id' => $event->refundId,
                    'booking_item_id' => $refund->bookingItemId,
                ]);

                return;
            }

            if ($commission->status === CommissionStatus::Reversed) {
                Log::info('Settlement: commission already fully reversed, skipping', [
                    'commission_id' => $commission->id,
                    'refund_id' => $event->refundId,
                ]);

                return;
            }

            try {
                $this->reverseCommission->execute($commission, $refund);
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    Log::info('Settlement: duplicate wallet ledger entry on refund reversal, skipping', [
                        'commission_id' => $commission->id,
                        'refund_id' => $event->refundId,
                    ]);

                    return;
                }

                throw $e;
            }
        } else {
            // Full payment refund — reverse all commissions for this payment
            $commissions = $this->commissionRepo->findByPaymentId($refund->paymentId);

            foreach ($commissions as $commission) {
                if ($commission->status === CommissionStatus::Reversed) {
                    continue;
                }

                try {
                    $this->reverseCommission->execute($commission, $refund);
                } catch (QueryException $e) {
                    if ($e->getCode() === '23000') {
                        Log::info('Settlement: duplicate wallet ledger entry on full refund reversal, skipping', [
                            'commission_id' => $commission->id,
                            'refund_id' => $event->refundId,
                        ]);

                        continue;
                    }

                    throw $e;
                }
            }
        }
    }
}
