<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Database\Seeders;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Enums\RuleKind;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds two active loyalty programs across two existing vendor profiles,
 * one FirstBooking rule per program, and a handful of sample earn entries
 * across two customers. Idempotent: skips when prerequisites are missing
 * or programs already exist.
 */
final class LoyaltyDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = VendorProfile::query()->take(2)->get();
        if ($vendors->count() < 2) {
            $this->command?->warn('LoyaltyDevelopmentSeeder: needs at least 2 vendor profiles; skipping.');

            return;
        }

        $customers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'customer'))
            ->take(2)
            ->get();
        if ($customers->count() < 2) {
            $this->command?->warn('LoyaltyDevelopmentSeeder: needs at least 2 customers; skipping.');

            return;
        }

        foreach ($vendors as $vendor) {
            $program = LoyaltyProgram::query()
                ->where('vendor_profile_id', $vendor->id)
                ->first();

            if ($program === null) {
                $program = LoyaltyProgram::create([
                    'public_id' => (string) Str::ulid(),
                    'vendor_profile_id' => $vendor->id,
                    'is_active' => true,
                    'points_per_currency_unit' => 1.0000,
                    'points_value_minor' => 100,
                    'points_value_currency' => 'EGP',
                    'min_points_to_redeem' => 100,
                    'max_redeem_pct' => 50,
                    'points_expire_after_days' => 365,
                    'name' => [
                        'en' => 'Loyalty rewards',
                        'ar' => 'مكافآت الولاء',
                    ],
                    'terms' => [
                        'en' => 'Earn points on every booking. Redeem for discounts.',
                        'ar' => 'اكسب نقاطًا في كل حجز. استبدلها بخصومات.',
                    ],
                ]);
            }

            $hasFirstBookingRule = LoyaltyRule::query()
                ->where('loyalty_program_id', $program->id)
                ->where('rule_kind', RuleKind::FirstBooking->value)
                ->exists();

            if (! $hasFirstBookingRule) {
                LoyaltyRule::create([
                    'public_id' => (string) Str::ulid(),
                    'loyalty_program_id' => $program->id,
                    'rule_kind' => RuleKind::FirstBooking,
                    'multiplier' => 2.00,
                    'conditions' => null,
                    'label' => [
                        'en' => 'First booking bonus 2x',
                        'ar' => 'مكافأة أول حجز 2x',
                    ],
                    'is_active' => true,
                    'starts_at' => null,
                    'ends_at' => null,
                ]);
            }

            // Seed sample earn entries (5 per program, alternating customers).
            $existingEntryCount = LoyaltyLedgerEntry::query()
                ->where('loyalty_program_id', $program->id)
                ->count();
            if ($existingEntryCount > 0) {
                continue;
            }

            $running = [];
            foreach (range(1, 5) as $i) {
                $customer = $customers[$i % 2];
                $points = 50 + ($i * 25);
                $running[$customer->id] = ($running[$customer->id] ?? 0) + $points;

                LoyaltyLedgerEntry::create([
                    'public_id' => (string) Str::ulid(),
                    'user_id' => $customer->id,
                    'vendor_profile_id' => $vendor->id,
                    'loyalty_program_id' => $program->id,
                    'direction' => LedgerDirection::Earn,
                    'points' => $points,
                    'balance_after' => $running[$customer->id],
                    'reference_type' => 'seeder',
                    'reference_id' => $i,
                    'reason' => [
                        'en' => 'Seeded earn #'.$i,
                        'ar' => 'إدخال مكتسب رقم '.$i,
                    ],
                    'expires_at' => now()->addDays(365),
                ]);
            }
        }
    }
}
