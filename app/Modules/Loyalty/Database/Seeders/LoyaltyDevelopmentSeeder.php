<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Database\Seeders;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Enums\LedgerEntryType;
use App\Modules\Loyalty\Domain\Enums\ProgramStatus;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class LoyaltyDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050311);

        DB::transaction(function (): void {
            $programs = $this->seedPrograms();
            $this->seedEarnLedger($programs);
            $this->seedRedemption($programs);
        });
    }

    /**
     * @return array<int, array{program: LoyaltyProgram, rule: LoyaltyRule}>
     */
    private function seedPrograms(): array
    {
        $rows = [
            'joy-rentals-cairo' => ['name' => ['en' => 'Joy Rewards', 'ar' => 'مكافآت جوي'], 'points' => 1, 'unit' => 1000],
            'sweet-table-studio' => ['name' => ['en' => 'Sweet Points', 'ar' => 'نقاط سويت'], 'points' => 2, 'unit' => 1000],
            'pixel-party-cards' => ['name' => ['en' => 'Pixel Club', 'ar' => 'نادي بيكسل'], 'points' => 1, 'unit' => 500],
        ];

        $programs = [];

        foreach ($rows as $slug => $row) {
            $vendor = VendorProfile::query()->where('slug', $slug)->firstOrFail();

            /** @var LoyaltyProgram $program */
            $program = $this->updateOrCreateFactoryModel(
                LoyaltyProgram::factory()->active()->make([
                    'public_id' => $this->stablePublicId('loyalty-program:'.$slug),
                    'vendor_profile_id' => $vendor->id,
                    'name' => $row['name'],
                    'terms' => [
                        'en' => 'Earn points on completed bookings and redeem them on future events.',
                        'ar' => 'اكسب نقاطا على الحجوزات المكتملة واستبدلها في مناسبات قادمة.',
                    ],
                    'currency' => 'EGP',
                    'status' => ProgramStatus::Active,
                    'expiration_days' => 180,
                    'created_by' => $vendor->user_id,
                ]),
                ['vendor_profile_id' => $vendor->id],
            );

            /** @var LoyaltyRule $rule */
            $rule = $this->updateOrCreateFactoryModel(
                LoyaltyRule::factory()->active()->make([
                    'public_id' => $this->stablePublicId('loyalty-rule:'.$slug.':default'),
                    'loyalty_program_id' => $program->id,
                    'label' => ['en' => 'Default earn and redeem rule', 'ar' => 'قاعدة الكسب والاستبدال الافتراضية'],
                    'earn_points_per_minor' => $row['points'],
                    'earn_minor_per_unit' => $row['unit'],
                    'redemption_ratio_points' => 100,
                    'redemption_ratio_minor' => 1000,
                    'min_points_to_redeem' => 100,
                    'max_redeem_pct_bps' => 3000,
                    'is_active' => true,
                    'effective_from' => '2026-05-01 00:00:00',
                ]),
                ['loyalty_program_id' => $program->id, 'effective_from' => '2026-05-01 00:00:00'],
            );

            $programs[$vendor->id] = ['program' => $program, 'rule' => $rule];
        }

        return $programs;
    }

    /**
     * @param  array<int, array{program: LoyaltyProgram, rule: LoyaltyRule}>  $programs
     */
    private function seedEarnLedger(array $programs): void
    {
        $booking = Booking::query()->where('reference_no', 'BK-DEV-1001')->firstOrFail();

        BookingItem::query()
            ->whereHas('bookingVendor.booking', fn ($query) => $query->where('reference_no', $booking->reference_no))
            ->with('bookingVendor')
            ->get()
            ->each(function (BookingItem $item) use ($programs, $booking): void {
                $vendorId = $item->bookingVendor->vendor_profile_id;
                $program = $programs[$vendorId]['program'] ?? null;
                $rule = $programs[$vendorId]['rule'] ?? null;

                if (! $program instanceof LoyaltyProgram || ! $rule instanceof LoyaltyRule) {
                    return;
                }

                $points = max(10, $rule->computeEarnPoints($item->line_total_minor - $item->commission_minor));

                $this->firstOrCreateFactoryModel(
                    LoyaltyLedgerEntry::factory()->earn()->make([
                        'public_id' => $this->stablePublicId('loyalty-ledger:earn:booking-item:'.$item->public_id),
                        'customer_id' => $booking->customer_id,
                        'vendor_profile_id' => $vendorId,
                        'loyalty_program_id' => $program->id,
                        'entry_type' => LedgerEntryType::Earn,
                        'points' => $points,
                        'booking_id' => $booking->id,
                        'booking_item_id' => $item->id,
                        'redemption_id' => null,
                        'reversed_from_ledger_id' => null,
                        'product_type' => $item->product_type,
                        'reason' => ['en' => 'Points earned from completed booking.', 'ar' => 'نقاط مكتسبة من حجز مكتمل.'],
                    ]),
                    ['entry_type' => LedgerEntryType::Earn->value, 'booking_item_id' => $item->id],
                );
            });
    }

    /**
     * @param  array<int, array{program: LoyaltyProgram, rule: LoyaltyRule}>  $programs
     */
    private function seedRedemption(array $programs): void
    {
        $booking = Booking::query()->where('reference_no', 'BK-DEV-1002')->firstOrFail();
        $vendor = VendorProfile::query()->where('slug', 'sweet-table-studio')->firstOrFail();
        $program = $programs[$vendor->id]['program'];
        $rule = $programs[$vendor->id]['rule'];
        $customer = User::query()->findOrFail($booking->customer_id);

        /** @var LoyaltyRedemption $redemption */
        $redemption = $this->updateOrCreateFactoryModel(
            LoyaltyRedemption::factory()->make([
                'public_id' => $this->stablePublicId('loyalty-redemption:'.$booking->reference_no.':sweet-table-studio'),
                'customer_id' => $customer->id,
                'vendor_profile_id' => $vendor->id,
                'loyalty_program_id' => $program->id,
                'loyalty_rule_id' => $rule->id,
                'booking_id' => $booking->id,
                'points_held' => 100,
                'discount_minor' => 1000,
                'discount_currency' => 'EGP',
                'status' => 'pending',
                'applied_at' => null,
                'voided_at' => null,
                'reversed_at' => null,
            ]),
            ['public_id' => $this->stablePublicId('loyalty-redemption:'.$booking->reference_no.':sweet-table-studio')],
        );

        $this->firstOrCreateFactoryModel(
            LoyaltyLedgerEntry::factory()->redeem()->make([
                'public_id' => $this->stablePublicId('loyalty-ledger:redeem:'.$redemption->public_id),
                'customer_id' => $customer->id,
                'vendor_profile_id' => $vendor->id,
                'loyalty_program_id' => $program->id,
                'entry_type' => LedgerEntryType::Redeem,
                'points' => -100,
                'booking_id' => $booking->id,
                'booking_item_id' => null,
                'redemption_id' => $redemption->id,
                'product_type' => null,
                'reason' => ['en' => 'Points reserved for booking discount.', 'ar' => 'نقاط محجوزة لخصم الحجز.'],
            ]),
            ['entry_type' => LedgerEntryType::Redeem->value, 'redemption_id' => $redemption->id],
        );
    }
}
