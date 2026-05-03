<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Factories;

use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
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
            'wallet_id' => 1, // overridden in tests
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
        return $this->state(['entry_type' => LedgerEntryType::CommissionCredit]);
    }

    public function refundDebit(): static
    {
        return $this->state(['entry_type' => LedgerEntryType::RefundDebit]);
    }

    public function withdrawalDebit(): static
    {
        return $this->state(['entry_type' => LedgerEntryType::WithdrawalDebit]);
    }
}
