<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\BookingItemSnapshotDto;
use App\Modules\Settlement\Application\DTOs\PaymentSnapshotDto;
use App\Modules\Settlement\Domain\Contracts\CommissionRateResolver;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Events\CommissionCalculated;
use App\Modules\Settlement\Domain\Models\Commission;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateCommissionAction
{
    public function __construct(
        private CommissionRateResolver $rateResolver,
        private CreditWalletAction $creditWallet,
    ) {}

    public function execute(BookingItemSnapshotDto $item, PaymentSnapshotDto $payment): Commission
    {
        return DB::transaction(function () use ($item, $payment) {
            // Rate: prefer snapshot on booking_item, fallback to resolver, default 0 with warning
            $hasSnapshotBps = $item->commissionBps !== null;
            $bps = $item->commissionBps
                ?? $this->rateResolver->resolve($item->categoryId, $item->productType)
                ?? 0;

            if ($bps === 0 && ! $hasSnapshotBps) {
                Log::warning('Settlement: no commission rate found, defaulting to 0', [
                    'category_id' => $item->categoryId,
                    'product_type' => $item->productType->value,
                    'booking_item_id' => $item->id,
                ]);
            }

            // Brick\Money math with HALF_EVEN rounding
            $gross = Money::ofMinor($item->totalMinor, $item->totalCurrency);
            $commissionMinor = $gross
                ->multipliedBy($bps / 10000, RoundingMode::HALF_EVEN)
                ->getMinorAmount()
                ->toInt();
            $vendorShareMinor = $item->totalMinor - $commissionMinor;

            /** @var Commission $commission */
            $commission = Commission::create([
                'booking_item_id' => $item->id,
                'payment_id' => $payment->id,
                'vendor_profile_id' => $item->vendorProfileId,
                'category_id' => $item->categoryId,
                'product_type' => $item->productType,
                'gross_amount_minor' => $item->totalMinor,
                'gross_amount_currency' => $item->totalCurrency,
                'commission_bps' => $bps,
                'commission_minor' => $commissionMinor,
                'commission_currency' => $item->totalCurrency,
                'vendor_share_minor' => $vendorShareMinor,
                'vendor_share_currency' => $item->totalCurrency,
                'status' => CommissionStatus::Calculated,
            ]);

            // Credit the vendor wallet with their share
            $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
            $this->creditWallet->execute(
                ownerType: $ownerType,
                ownerId: $item->vendorProfileId,
                amountMinor: $vendorShareMinor,
                currency: $item->totalCurrency,
                entryType: LedgerEntryType::CommissionCredit,
                relatedEntityType: 'commission',
                relatedEntityId: $commission->id,
                descriptionKey: 'settlement::settlement.ledger.commission_credit',
                descriptionParams: ['booking_item_id' => $item->id],
            );

            DB::afterCommit(fn () => event(new CommissionCalculated(
                commissionId: $commission->id,
                vendorProfileId: $item->vendorProfileId,
                vendorShareMinor: $vendorShareMinor,
                currency: $item->totalCurrency,
            )));

            return $commission;
        });
    }
}
