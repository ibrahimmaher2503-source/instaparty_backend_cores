<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Factories;

use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    protected $model = Withdrawal::class;

    public function definition(): array
    {
        $vendorProfileId = 1; // overridden in tests

        return [
            'public_id' => (string) Str::ulid(),
            'vendor_profile_id' => $vendorProfileId,
            'requested_amount_minor' => $this->faker->numberBetween(10000, 100000),
            'requested_amount_currency' => 'EGP',
            'paid_amount_minor' => null,
            'paid_amount_currency' => null,
            'bank_account_snapshot' => new BankAccountSnapshot(
                account_holder: $this->faker->name(),
                iban: 'EG380019000500000000263180002', // valid test IBAN
                bank_name: 'Test Bank',
                swift_bic: 'TESTEGCX',
            ),
            'status' => WithdrawalStatus::Pending,
            'rejected_reason' => null,
            'requested_by_user_id' => 1, // overridden in tests
            'processed_by_user_id' => null,
            'bank_proof_media_id' => null,
            'requested_at' => now(),
            'processed_at' => null,
            'paid_at' => null,
            'pending_lock' => $vendorProfileId,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WithdrawalStatus::Pending,
            'pending_lock' => $attributes['vendor_profile_id'],
        ]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => WithdrawalStatus::Approved,
            'pending_lock' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WithdrawalStatus::Paid,
            'pending_lock' => null,
            'paid_amount_minor' => $attributes['requested_amount_minor'],
            'paid_amount_currency' => $attributes['requested_amount_currency'],
            'paid_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => WithdrawalStatus::Rejected,
            'pending_lock' => null,
            'rejected_reason' => ['en' => 'Rejected by admin.', 'ar' => 'رُفض بواسطة المشرف.'],
            'processed_at' => now(),
        ]);
    }
}
