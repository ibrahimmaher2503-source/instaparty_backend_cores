<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Factories;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\ApprovedState;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\PaidState;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\PendingState;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\RejectedState;
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
        return [
            'public_id' => (string) Str::ulid(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
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
            'status' => PendingState::class,
            'rejected_reason' => null,
            'requested_by_user_id' => User::factory()->asVendor(),
            'processed_by_user_id' => null,
            'bank_proof_media_id' => null,
            'requested_at' => now(),
            'processed_at' => null,
            'paid_at' => null,
            'pending_lock' => fn (array $attributes): int => (int) $attributes['vendor_profile_id'],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PendingState::class,
            'pending_lock' => $attributes['vendor_profile_id'],
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'               => ApprovedState::class,
            'pending_lock'         => null,
            'approved_at'          => now(),
            'approved_by_admin_id' => User::factory()->create()->id,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'                  => PaidState::class,
            'pending_lock'            => null,
            'paid_amount_minor'       => $attributes['requested_amount_minor'],
            'paid_amount_currency'    => $attributes['requested_amount_currency'],
            'approved_at'             => now()->subHour(),
            'approved_by_admin_id'    => User::factory()->create()->id,
            'paid_at'                 => now(),
            'paid_by_admin_id'        => User::factory()->create()->id,
            'processed_at'            => now(),
            'bank_transfer_reference' => 'EGTBNK-FACTORY-'.mt_rand(10000, 99999),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => RejectedState::class,
            'pending_lock' => null,
            'rejected_reason' => ['en' => 'Rejected by admin.', 'ar' => 'رُفض بواسطة المشرف.'],
            'processed_at' => now(),
        ]);
    }
}
