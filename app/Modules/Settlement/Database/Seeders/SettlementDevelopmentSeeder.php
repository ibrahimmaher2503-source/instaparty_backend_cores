<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Seeders;

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SettlementRunStatus;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\SettlementRun;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SettlementDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050307);

        DB::transaction(function (): void {
            $payment = Payment::query()->where('gateway', 'paymob')->where('gateway_ref', 'PAY-DEV-1001')->firstOrFail();
            $admin = User::query()->where('email', 'admin@instaparty.local')->firstOrFail();

            $commissions = $this->seedCommissions($payment);
            $wallets = $this->seedWallets($commissions);
            $withdrawals = $this->seedWithdrawals($wallets, $admin);

            $this->seedWithdrawalLedger($wallets, $withdrawals);
            $this->seedSettlementRun($commissions);
        });
    }

    /**
     * @return Collection<int, Commission>
     */
    private function seedCommissions(Payment $payment): Collection
    {
        return BookingItem::query()
            ->whereHas('bookingVendor.booking', fn ($query) => $query->where('reference_no', 'BK-DEV-1001'))
            ->with('bookingVendor')
            ->get()
            ->map(function (BookingItem $item) use ($payment): Commission {
                $bookingVendor = $item->bookingVendor;
                $service = Service::query()->findOrFail($item->service_id);

                /** @var Commission $commission */
                $commission = $this->firstOrCreateFactoryModel(
                    Commission::factory()->make([
                        'public_id' => $this->stablePublicId('commission:booking-item:'.$item->public_id),
                        'booking_item_id' => $item->id,
                        'payment_id' => $payment->id,
                        'vendor_profile_id' => $bookingVendor->vendor_profile_id,
                        'category_id' => $service->category_id,
                        'product_type' => $item->product_type,
                        'gross_amount_minor' => $item->line_total_minor,
                        'gross_amount_currency' => $item->line_total_currency,
                        'commission_bps' => $item->commission_bps,
                        'commission_minor' => $item->commission_minor,
                        'commission_currency' => $item->commission_currency,
                        'vendor_share_minor' => $item->line_total_minor - $item->commission_minor,
                        'vendor_share_currency' => $item->line_total_currency,
                        'reversed_amount_minor' => 0,
                        'status' => CommissionStatus::Calculated,
                    ]),
                    ['booking_item_id' => $item->id],
                );

                return $commission;
            });
    }

    /**
     * @param  Collection<int, Commission>  $commissions
     * @return array<int, Wallet>
     */
    private function seedWallets(Collection $commissions): array
    {
        $wallets = [];

        foreach ($commissions->groupBy('vendor_profile_id') as $vendorId => $vendorCommissions) {
            $pendingWithdrawalMinor = $vendorCommissions->sum('vendor_share_minor') > 100000 ? 50000 : 0;

            /** @var Wallet $wallet */
            $wallet = $this->updateOrCreateFactoryModel(
                Wallet::factory()->make([
                    'public_id' => $this->stablePublicId('wallet:vendor:'.$vendorId.':EGP'),
                    'owner_type' => VendorProfile::class,
                    'owner_id' => (int) $vendorId,
                    'currency' => 'EGP',
                    'balance_minor' => $vendorCommissions->sum('vendor_share_minor') - $pendingWithdrawalMinor,
                    'pending_withdrawal_minor' => $pendingWithdrawalMinor,
                ]),
                ['owner_type' => VendorProfile::class, 'owner_id' => (int) $vendorId, 'currency' => 'EGP'],
            );

            foreach ($vendorCommissions as $commission) {
                $this->firstOrCreateFactoryModel(
                    WalletLedgerEntry::factory()->commissionCredit()->make([
                        'wallet_id' => $wallet->id,
                        'amount_minor' => $commission->vendor_share_minor,
                        'currency' => $commission->vendor_share_currency,
                        'description_params' => ['commission_public_id' => $commission->public_id],
                        'related_entity_type' => Commission::class,
                        'related_entity_id' => $commission->id,
                    ]),
                    [
                        'related_entity_type' => Commission::class,
                        'related_entity_id' => $commission->id,
                        'entry_type' => LedgerEntryType::CommissionCredit->value,
                    ],
                );
            }

            $wallets[(int) $vendorId] = $wallet;
        }

        return $wallets;
    }

    /**
     * @param  array<int, Wallet>  $wallets
     * @return array<string, Withdrawal>
     */
    private function seedWithdrawals(array $wallets, User $admin): array
    {
        $rows = [
            'joy-rentals-cairo' => ['amount' => 50000, 'status' => WithdrawalStatus::Paid, 'requested' => '2026-05-22 09:00:00'],
            'sweet-table-studio' => ['amount' => 50000, 'status' => WithdrawalStatus::Pending, 'requested' => '2026-05-23 10:00:00'],
        ];

        $withdrawals = [];

        foreach ($rows as $slug => $row) {
            $vendor = VendorProfile::query()->where('slug', $slug)->firstOrFail();

            if (! isset($wallets[$vendor->id])) {
                continue;
            }

            $factory = Withdrawal::factory();

            if ($row['status'] === WithdrawalStatus::Paid) {
                $factory = $factory->paid();
            }

            /** @var Withdrawal $withdrawal */
            $withdrawal = $this->updateOrCreateFactoryModel(
                $factory->make([
                    'public_id' => $this->stablePublicId('withdrawal:'.$slug.':'.$row['status']->value),
                    'vendor_profile_id' => $vendor->id,
                    'requested_amount_minor' => $row['amount'],
                    'requested_amount_currency' => 'EGP',
                    'paid_amount_minor' => $row['status'] === WithdrawalStatus::Paid ? $row['amount'] : null,
                    'paid_amount_currency' => $row['status'] === WithdrawalStatus::Paid ? 'EGP' : null,
                    'status' => $row['status'],
                    'requested_by_user_id' => $vendor->user_id,
                    'processed_by_user_id' => $row['status'] === WithdrawalStatus::Paid ? $admin->id : null,
                    'requested_at' => $row['requested'],
                    'processed_at' => $row['status'] === WithdrawalStatus::Paid ? '2026-05-22 13:00:00' : null,
                    'paid_at' => $row['status'] === WithdrawalStatus::Paid ? '2026-05-22 15:00:00' : null,
                    'pending_lock' => $row['status'] === WithdrawalStatus::Pending ? $vendor->id : null,
                ]),
                ['public_id' => $this->stablePublicId('withdrawal:'.$slug.':'.$row['status']->value)],
            );

            $withdrawals[$slug] = $withdrawal;
        }

        return $withdrawals;
    }

    /**
     * @param  array<int, Wallet>  $wallets
     * @param  array<string, Withdrawal>  $withdrawals
     */
    private function seedWithdrawalLedger(array $wallets, array $withdrawals): void
    {
        foreach ($withdrawals as $withdrawal) {
            if ($withdrawal->status !== WithdrawalStatus::Paid) {
                continue;
            }

            $wallet = $wallets[$withdrawal->vendor_profile_id] ?? null;

            if ($wallet === null) {
                continue;
            }

            $this->firstOrCreateFactoryModel(
                WalletLedgerEntry::factory()->withdrawalDebit()->make([
                    'wallet_id' => $wallet->id,
                    'amount_minor' => -abs((int) $withdrawal->paid_amount_minor),
                    'currency' => (string) $withdrawal->paid_amount_currency,
                    'description_params' => ['withdrawal_public_id' => $withdrawal->public_id],
                    'related_entity_type' => Withdrawal::class,
                    'related_entity_id' => $withdrawal->id,
                ]),
                [
                    'related_entity_type' => Withdrawal::class,
                    'related_entity_id' => $withdrawal->id,
                    'entry_type' => LedgerEntryType::WithdrawalDebit->value,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Commission>  $commissions
     */
    private function seedSettlementRun(Collection $commissions): void
    {
        $this->updateOrCreateFactoryModel(
            SettlementRun::factory()->reconciled()->make([
                'public_id' => $this->stablePublicId('settlement-run:2026-05'),
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
                'total_gross_minor' => $commissions->sum('gross_amount_minor'),
                'total_gross_currency' => 'EGP',
                'total_commission_minor' => $commissions->sum('commission_minor'),
                'total_commission_currency' => 'EGP',
                'total_vendor_share_minor' => $commissions->sum('vendor_share_minor'),
                'total_vendor_share_currency' => 'EGP',
                'status' => SettlementRunStatus::Reconciled,
            ]),
            ['public_id' => $this->stablePublicId('settlement-run:2026-05')],
        );
    }
}
