<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Factories;

use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletLedgerEntry>
 */
class WalletLedgerEntryFactory extends Factory
{
    protected $model = WalletLedgerEntry::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'entry_type' => LedgerEntryType::CommissionCredit,
            'amount_minor' => $this->faker->numberBetween(1000, 100000),
            'currency' => 'EGP',
            'description_key' => 'settlement.ledger.commission_credit',
            'description_params' => null,
            'related_entity_type' => null,
            'related_entity_id' => null,
        ];
    }

    public function commissionCredit(): static
    {
        return $this->state([
            'entry_type' => LedgerEntryType::CommissionCredit,
            'direction' => 'credit',
            'description_key' => 'settlement.ledger.commission_credit',
        ]);
    }

    public function refundDebit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'entry_type' => LedgerEntryType::RefundDebit,
            'direction' => 'debit',
            'amount_minor' => abs((int) ($attributes['amount_minor'] ?? $this->faker->numberBetween(1000, 50000))),
            'description_key' => 'settlement.ledger.refund_debit',
        ]);
    }

    public function withdrawalDebit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'entry_type' => LedgerEntryType::WithdrawalDebit,
            'direction' => 'debit',
            'amount_minor' => abs((int) ($attributes['amount_minor'] ?? $this->faker->numberBetween(10000, 100000))),
            'description_key' => 'settlement.ledger.withdrawal_debit',
        ]);
    }
}
