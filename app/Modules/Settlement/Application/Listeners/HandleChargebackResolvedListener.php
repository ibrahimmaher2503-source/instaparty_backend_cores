<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Listeners;

use App\Modules\Payments\Domain\Enums\ChargebackStatus;
use App\Modules\Payments\Domain\Events\ChargebackResolved;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HandleChargebackResolvedListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly EloquentWalletRepository $walletRepo,
    ) {}

    public function handle(ChargebackResolved $event): void
    {
        $commission = DB::table('commissions')
            ->where('payment_id', $event->paymentId)
            ->whereNotNull('vendor_wallet_id')
            ->first();

        if ($commission === null) {
            Log::warning('Settlement: ChargebackResolved — no commission row found for payment', [
                'payment_id' => $event->paymentId,
            ]);

            return;
        }

        $chargeback = DB::table('payment_chargebacks')->find($event->chargebackId);

        if ($chargeback === null) {
            return;
        }

        match ($event->resolution) {
            // Chargeback won = platform wins = restore vendor wallet credit
            ChargebackStatus::Won => $this->restoreVendorCredit($commission, $chargeback),
            // Chargeback lost = platform loses = finalize the debit (no further action)
            ChargebackStatus::Lost => $this->finalizeDebit($commission, $chargeback),
            default => null,
        };
    }

    private function restoreVendorCredit(object $commission, object $chargeback): void
    {
        $wallet = Wallet::find($commission->vendor_wallet_id);

        if ($wallet === null) {
            return;
        }

        DB::transaction(function () use ($wallet, $chargeback): void {
            DB::table('wallet_ledger')->insert([
                'public_id' => (string) Str::ulid(),
                'wallet_id' => $wallet->id,
                'amount_minor' => $chargeback->amount_minor,
                'currency' => $chargeback->amount_currency,
                'direction' => 'credit',
                'entry_type' => 'chargeback_won_restoration',
                'reference_type' => 'payment_chargebacks',
                'reference_id' => $chargeback->id,
                'created_at' => now(),
            ]);

            $this->walletRepo->incrementBalance($wallet->id, $chargeback->amount_minor);
        });
    }

    private function finalizeDebit(object $commission, object $chargeback): void
    {
        // The debit was already applied when ChargebackOpened fired.
        // Chargeback lost = debit stands. Log for audit only.
        Log::info('Settlement: chargeback lost — vendor debit finalized', [
            'chargeback_id' => $chargeback->id,
            'amount_minor' => $chargeback->amount_minor,
        ]);
    }
}
