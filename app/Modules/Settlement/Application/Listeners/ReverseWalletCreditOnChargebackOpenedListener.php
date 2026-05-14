<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Listeners;

use App\Modules\Payments\Domain\Events\ChargebackOpened;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReverseWalletCreditOnChargebackOpenedListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly EloquentWalletRepository $walletRepo,
    ) {}

    public function handle(ChargebackOpened $event): void
    {
        // Find the vendor wallet that received the credit for this payment and debit it back
        $commission = DB::table('commissions')
            ->where('payment_id', $event->paymentId)
            ->whereNotNull('vendor_wallet_id')
            ->first();

        if ($commission === null) {
            Log::warning('Settlement: ChargebackOpened — no commission row found for payment', [
                'payment_id' => $event->paymentId,
            ]);

            return;
        }

        $wallet = Wallet::find($commission->vendor_wallet_id);

        if ($wallet === null) {
            Log::error('Settlement: ChargebackOpened — vendor wallet not found', [
                'wallet_id' => $commission->vendor_wallet_id,
            ]);

            return;
        }

        DB::transaction(function () use ($event, $wallet): void {
            DB::table('wallet_ledger')->insert([
                'public_id' => (string) Str::ulid(),
                'wallet_id' => $wallet->id,
                'amount_minor' => -$event->amountMinor,
                'currency' => $event->amountCurrency,
                'direction' => 'debit',
                'entry_type' => 'chargeback_reversal',
                'reference_type' => 'payment_chargebacks',
                'reference_id' => $event->chargebackId,
                'created_at' => now(),
            ]);

            $this->walletRepo->decrementBalance($wallet->id, $event->amountMinor);
        });
    }
}
