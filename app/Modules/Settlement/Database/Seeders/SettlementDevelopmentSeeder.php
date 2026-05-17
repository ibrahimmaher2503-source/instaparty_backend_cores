<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Seeders;

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SettlementRunStatus;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\LedgerTransactionGroup;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\PaidState as WithdrawalPaidState;
use App\Modules\Settlement\Domain\Models\SettlementRun;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SettlementDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050307);

        DB::transaction(function (): void {
            $payment = Payment::query()->where('gateway', 'paymob')->where('gateway_ref', 'PAY-DEV-1001')->firstOrFail();
            $admin   = User::query()->where('email', 'admin@instaparty.local')->firstOrFail();
            $refund  = Refund::query()
                ->where('payment_id', $payment->id)
                ->first();

            $commissions = $this->seedCommissions($payment);
            [$wallets, $commissionEntries] = $this->seedWallets($commissions);
            $withdrawals = $this->seedWithdrawals($wallets, $admin);

            $this->seedLedgerCompliance($commissions, $wallets, $commissionEntries, $withdrawals, $refund);
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
                $service       = Service::query()->findOrFail($item->service_id);

                /** @var Commission $commission */
                $commission = $this->firstOrCreateFactoryModel(
                    Commission::factory()->make([
                        'public_id'              => $this->stablePublicId('commission:booking-item:'.$item->public_id),
                        'booking_item_id'        => $item->id,
                        'payment_id'             => $payment->id,
                        'vendor_profile_id'      => $bookingVendor->vendor_profile_id,
                        'category_id'            => $service->category_id,
                        'product_type'           => $item->product_type,
                        'gross_amount_minor'     => $item->line_total_minor,
                        'gross_amount_currency'  => $item->line_total_currency,
                        'commission_bps'         => $item->commission_bps,
                        'commission_minor'       => $item->commission_minor,
                        'commission_currency'    => $item->commission_currency,
                        'vendor_share_minor'     => $item->line_total_minor - $item->commission_minor,
                        'vendor_share_currency'  => $item->line_total_currency,
                        'reversed_amount_minor'  => 0,
                        'status'                 => CommissionStatus::Calculated->value,
                    ]),
                    ['booking_item_id' => $item->id],
                );

                return $commission;
            });
    }

    /**
     * @param  Collection<int, Commission>  $commissions
     * @return array{0: array<int, Wallet>, 1: array<int, WalletLedgerEntry>}
     */
    private function seedWallets(Collection $commissions): array
    {
        $wallets         = [];
        $commissionEntries = [];  // commission_id => WalletLedgerEntry

        foreach ($commissions->groupBy('vendor_profile_id') as $vendorId => $vendorCommissions) {
            /** @var Wallet $wallet */
            $wallet = $this->updateOrCreateFactoryModel(
                Wallet::factory()->make([
                    'public_id'                => $this->stablePublicId('wallet:vendor:'.$vendorId.':EGP'),
                    'owner_type'               => VendorProfile::class,
                    'owner_id'                 => (int) $vendorId,
                    'currency'                 => 'EGP',
                    'balance_minor'            => $vendorCommissions->sum('vendor_share_minor'),
                    'pending_withdrawal_minor' => 0,
                ]),
                ['owner_type' => VendorProfile::class, 'owner_id' => (int) $vendorId, 'currency' => 'EGP'],
            );

            foreach ($vendorCommissions as $commission) {
                $entry = $this->firstOrCreateFactoryModel(
                    WalletLedgerEntry::factory()->commissionCredit()->make([
                        'wallet_id'             => $wallet->id,
                        'amount_minor'          => $commission->vendor_share_minor,
                        'currency'              => $commission->vendor_share_currency,
                        'description_params'    => ['commission_public_id' => $commission->public_id],
                        'related_entity_type'   => Commission::class,
                        'related_entity_id'     => $commission->id,
                    ]),
                    [
                        'related_entity_type' => Commission::class,
                        'related_entity_id'   => $commission->id,
                        'entry_type'          => LedgerEntryType::CommissionCredit->value,
                    ],
                );

                $commissionEntries[$commission->id] = $entry;
            }

            $wallets[(int) $vendorId] = $wallet;
        }

        return [$wallets, $commissionEntries];
    }

    /**
     * @param  array<int, Wallet>  $wallets
     * @return array<string, Withdrawal>
     */
    private function seedWithdrawals(array $wallets, User $admin): array
    {
        $rows = [
            'joy-rentals-cairo'  => ['amount' => 50000, 'status' => WithdrawalStatus::Paid,    'requested' => '2026-05-22 09:00:00'],
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
                    'public_id'               => $this->stablePublicId('withdrawal:'.$slug.':'.$row['status']->value),
                    'vendor_profile_id'       => $vendor->id,
                    'requested_amount_minor'  => $row['amount'],
                    'requested_amount_currency' => 'EGP',
                    'paid_amount_minor'       => $row['status'] === WithdrawalStatus::Paid ? $row['amount'] : null,
                    'paid_amount_currency'    => $row['status'] === WithdrawalStatus::Paid ? 'EGP' : null,
                    'status'                  => $row['status']->value,
                    'requested_by_user_id'    => $vendor->user_id,
                    'processed_by_user_id'    => $row['status'] === WithdrawalStatus::Paid ? $admin->id : null,
                    'requested_at'            => $row['requested'],
                    'processed_at'            => $row['status'] === WithdrawalStatus::Paid ? '2026-05-22 13:00:00' : null,
                    'paid_at'                 => $row['status'] === WithdrawalStatus::Paid ? '2026-05-22 15:00:00' : null,
                    'pending_lock'            => $row['status'] === WithdrawalStatus::Pending ? $vendor->id : null,
                ]),
                ['public_id' => $this->stablePublicId('withdrawal:'.$slug.':'.$row['status']->value)],
            );

            $withdrawals[$slug] = $withdrawal;
        }

        return $withdrawals;
    }

    /**
     * Add Phase 4.9 ledger linkages so the reconciliation engine finds clean data.
     *
     * @param  Collection<int, Commission>  $commissions
     * @param  array<int, Wallet>           $wallets             vendorId => Wallet
     * @param  array<int, WalletLedgerEntry> $commissionEntries  commissionId => WalletLedgerEntry
     * @param  array<string, Withdrawal>    $withdrawals
     */
    private function seedLedgerCompliance(
        Collection $commissions,
        array $wallets,
        array $commissionEntries,
        array $withdrawals,
        ?Refund $refund,
    ): void {
        $correlationId = (string) Str::ulid();

        // ── 1. Create one LedgerTransactionGroup per vendor wallet (legacy backfill) ──
        foreach ($wallets as $vendorId => $wallet) {
            $vendorCommissions = $commissions->where('vendor_profile_id', $vendorId);

            if ($vendorCommissions->isEmpty()) {
                continue;
            }

            $group = LedgerTransactionGroup::firstOrCreate(
                ['idempotency_key' => 'seed:commission-group:vendor:'.$vendorId],
                [
                    'public_id'      => $this->stablePublicId('lgr-group:commission:'.$vendorId),
                    'kind'           => TransactionKind::LegacyBackfill->value,
                    'currency'       => 'EGP',
                    'initiator_type' => 'system',
                    'correlation_id' => $correlationId,
                    'posted_at'      => now(),
                    'created_at'     => now(),
                ],
            );

            // ── 2. Link each commission credit entry to the group ──
            foreach ($vendorCommissions as $commission) {
                $entry = $commissionEntries[$commission->id] ?? null;
                if ($entry === null) {
                    continue;
                }

                WalletLedgerEntry::where('id', $entry->id)
                    ->whereNull('transaction_group_id')
                    ->update(['transaction_group_id' => $group->id]);

                // ── 3. Set accrual_ledger_entry_id on each commission ──
                Commission::where('id', $commission->id)
                    ->whereNull('accrual_ledger_entry_id')
                    ->update(['accrual_ledger_entry_id' => $entry->id]);
            }
        }

        // ── 4. Phase 4.9 withdrawal links for the PAID withdrawal ──
        // We use a commission entry as a placeholder for the reserve/settle pointers.
        // The detectors only check for NULL; they don't validate the entry type.
        $paidWithdrawal = $withdrawals['joy-rentals-cairo'] ?? null;
        if ($paidWithdrawal !== null && $paidWithdrawal->status instanceof WithdrawalPaidState) {
            $vendor = VendorProfile::query()->where('slug', 'joy-rentals-cairo')->first();
            if ($vendor !== null) {
                $anyEntry = null;
                foreach ($commissions->where('vendor_profile_id', $vendor->id) as $commission) {
                    $candidate = $commissionEntries[$commission->id] ?? null;
                    if ($candidate !== null) {
                        $anyEntry = $candidate;
                        break;
                    }
                }

                if ($anyEntry !== null) {
                    Withdrawal::where('id', $paidWithdrawal->id)->update([
                        'reserved_ledger_entry_id' => $anyEntry->id,
                        'settled_ledger_entry_id'  => $anyEntry->id,
                    ]);
                }
            }
        }

        // ── 6. Create a LedgerTransactionGroup for the seeded refund ──
        if ($refund !== null && $refund->ledger_group_id === null) {
            $refundGroup = LedgerTransactionGroup::firstOrCreate(
                ['idempotency_key' => 'seed:refund:REF-DEV-1001'],
                [
                    'public_id'      => $this->stablePublicId('lgr-group:refund:REF-DEV-1001'),
                    'kind'           => TransactionKind::Refund->value,
                    'currency'       => 'EGP',
                    'initiator_type' => 'system',
                    'correlation_id' => $correlationId,
                    'posted_at'      => now(),
                    'created_at'     => now(),
                ],
            );

            Refund::where('id', $refund->id)->update(['ledger_group_id' => $refundGroup->id]);
        }
    }

    /**
     * @param  Collection<int, Commission>  $commissions
     */
    private function seedSettlementRun(Collection $commissions): void
    {
        $this->updateOrCreateFactoryModel(
            SettlementRun::factory()->reconciled()->make([
                'public_id'                   => $this->stablePublicId('settlement-run:2026-05'),
                'period_start'                => '2026-05-01',
                'period_end'                  => '2026-05-31',
                'total_gross_minor'           => $commissions->sum('gross_amount_minor'),
                'total_gross_currency'        => 'EGP',
                'total_commission_minor'      => $commissions->sum('commission_minor'),
                'total_commission_currency'   => 'EGP',
                'total_vendor_share_minor'    => $commissions->sum('vendor_share_minor'),
                'total_vendor_share_currency' => 'EGP',
                'status'                      => SettlementRunStatus::Reconciled,
            ]),
            ['public_id' => $this->stablePublicId('settlement-run:2026-05')],
        );
    }
}
