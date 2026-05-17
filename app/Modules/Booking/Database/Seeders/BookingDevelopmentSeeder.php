<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Seeders;

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingCustomerNote;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingLock;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use App\Modules\Booking\Domain\Models\BookingSnapshot;
use App\Modules\Shared\Domain\Models\StateTransition;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BookingDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050305);

        DB::transaction(function (): void {
            $birthday = Occasion::query()->where('code', 'birthday')->firstOrFail();
            $nasrCity = City::query()->where('name->en', 'Nasr City')->first()
                ?? City::query()->orderBy('id')->firstOrFail();
            $dokki = City::query()->where('name->en', 'Dokki')->first()
                ?? City::query()->orderByDesc('id')->firstOrFail();
            $customerOne = User::query()->where('email', 'customer.one@instaparty.local')->firstOrFail();
            $customerTwo = User::query()->where('email', 'customer.two@instaparty.local')->firstOrFail();
            $customerThree = User::query()->where('email', 'customer.three@instaparty.local')->firstOrFail();

            $completed = $this->seedBooking(
                reference: 'BK-DEV-1001',
                customer: $customerOne,
                occasion: $birthday,
                city: $nasrCity,
                lifecycle: LifecycleStatus::Completed,
                payment: PaymentStatus::PartiallyRefunded,
                fulfillment: FulfillmentStatus::Completed,
                eventStartsAt: '2026-05-20 18:00:00',
                eventEndsAt: '2026-05-20 23:00:00',
                totalMinor: 400000,
                amountPaidMinor: 400000,
            );

            $this->seedBookingVendorWithItem(
                booking: $completed,
                vendor: VendorProfile::query()->where('slug', 'joy-rentals-cairo')->firstOrFail(),
                service: Service::query()->where('slug', 'joy-castle-10x10')->firstOrFail(),
                status: VendorSubStatus::Completed,
                itemStatus: 'picked_up',
                unitPriceMinor: 250000,
                deliveryFeeMinor: 10000,
                commissionBps: 1500,
                productType: ProductType::Rental,
            );

            $this->seedBookingVendorWithItem(
                booking: $completed,
                vendor: VendorProfile::query()->where('slug', 'sweet-table-studio')->firstOrFail(),
                service: Service::query()->where('slug', 'deluxe-birthday-cake')->firstOrFail(),
                status: VendorSubStatus::Completed,
                itemStatus: 'delivered',
                unitPriceMinor: 90000,
                deliveryFeeMinor: 5000,
                commissionBps: 1800,
                productType: ProductType::Sale,
            );

            $this->seedBookingVendorWithItem(
                booking: $completed,
                vendor: VendorProfile::query()->where('slug', 'pixel-party-cards')->firstOrFail(),
                service: Service::query()->where('slug', 'animated-birthday-invite')->firstOrFail(),
                status: VendorSubStatus::Completed,
                itemStatus: 'redeemed',
                unitPriceMinor: 45000,
                deliveryFeeMinor: 0,
                commissionBps: 1500,
                productType: ProductType::Digital,
            );

            $submitted = $this->seedBooking(
                reference: 'BK-DEV-1002',
                customer: $customerTwo,
                occasion: $birthday,
                city: $dokki,
                lifecycle: LifecycleStatus::VendorReview,
                payment: PaymentStatus::Unpaid,
                fulfillment: FulfillmentStatus::NotStarted,
                eventStartsAt: '2026-05-28 19:00:00',
                eventEndsAt: '2026-05-28 23:00:00',
                totalMinor: 165000,
                amountPaidMinor: 0,
            );

            $submittedVendor = $this->seedBookingVendorWithItem(
                booking: $submitted,
                vendor: VendorProfile::query()->where('slug', 'sweet-table-studio')->firstOrFail(),
                service: Service::query()->where('slug', 'dessert-table-set')->firstOrFail(),
                status: VendorSubStatus::Pending,
                itemStatus: 'pending',
                unitPriceMinor: 160000,
                deliveryFeeMinor: 5000,
                commissionBps: 1800,
                productType: ProductType::Sale,
            );

            $this->seedModification($submittedVendor);
            $this->seedOpenPaymentLock($submitted, $customerTwo);

            $active = $this->seedBooking(
                reference: 'BK-DEV-1003',
                customer: $customerThree,
                occasion: $birthday,
                city: $nasrCity,
                lifecycle: LifecycleStatus::Active,
                payment: PaymentStatus::Paid,
                fulfillment: FulfillmentStatus::InProgress,
                eventStartsAt: '2026-06-02 20:00:00',
                eventEndsAt: '2026-06-02 22:00:00',
                totalMinor: 30000,
                amountPaidMinor: 30000,
            );

            $this->seedBookingVendorWithItem(
                booking: $active,
                vendor: VendorProfile::query()->where('slug', 'pixel-party-cards')->firstOrFail(),
                service: Service::query()->where('slug', 'party-game-pack')->firstOrFail(),
                status: VendorSubStatus::InProgress,
                itemStatus: 'sent',
                unitPriceMinor: 30000,
                deliveryFeeMinor: 0,
                commissionBps: 1500,
                productType: ProductType::Digital,
            );

            $this->seedSnapshot($completed, 1, 'booking_created', ['status' => 'draft', 'items' => 0]);
            $this->seedSnapshot($completed, 2, 'booking_submitted', ['status' => 'submitted', 'items' => 3]);
            $this->seedSnapshot($completed, 3, 'booking_confirmed', ['status' => 'confirmed', 'items' => 3]);
            $this->seedSnapshot($completed, 4, 'payment_captured', ['status' => 'completed', 'items' => 3]);

            $this->seedSnapshot($submitted, 1, 'booking_created', ['status' => 'draft', 'items' => 0]);
            $this->seedSnapshot($submitted, 2, 'booking_submitted', ['status' => 'submitted', 'items' => 1]);
            $this->seedSnapshot($active, 1, 'booking_created', ['status' => 'draft', 'items' => 0]);
            $this->seedSnapshot($active, 2, 'booking_confirmed', ['status' => 'active', 'items' => 1]);

            $this->seedCustomerNote($completed, $customerOne, 'Please keep the castle near the garden entrance.', 'en');
            $this->seedCustomerNote($completed, $customerOne, 'يرجى وضع القلعة قرب مدخل الحديقة.', 'ar');
            $this->seedCustomerNote($submitted, $customerTwo, 'Please keep the dessert table simple and elegant.', 'en');
            $this->seedCustomerNote($active, $customerThree, 'Can you send the invite to WhatsApp as well?', 'en');

            $this->seedTransition($submitted, 'draft', 'submitted', 'customer', '2026-05-18 08:30:00');
            $this->seedTransition($completed, 'active', 'completed', 'system', '2026-05-20 23:45:00');
        });
    }

    private function seedBooking(
        string $reference,
        User $customer,
        Occasion $occasion,
        City $city,
        LifecycleStatus $lifecycle,
        PaymentStatus $payment,
        FulfillmentStatus $fulfillment,
        string $eventStartsAt,
        string $eventEndsAt,
        int $totalMinor,
        int $amountPaidMinor,
    ): Booking {
        /** @var Booking $booking */
        $booking = $this->updateOrCreateFactoryModel(
            Booking::factory()->make([
                'public_id' => $this->stablePublicId('booking:'.$reference),
                'reference_no' => $reference,
                'customer_id' => $customer->id,
                'occasion_id' => $occasion->id,
                'lifecycle_status' => $lifecycle->value,
                'payment_status' => $payment->value,
                'fulfillment_status' => $fulfillment,
                'event_starts_at' => $eventStartsAt,
                'event_ends_at' => $eventEndsAt,
                'guest_count' => $reference === 'BK-DEV-1002' ? 20 : 80,
                'theme' => ['code' => $reference === 'BK-DEV-1002' ? 'luxury-gold' : 'classic-party'],
                'celebrant_name' => $reference === 'BK-DEV-1003' ? 'Lina' : 'Adam',
                'celebrant_dob' => '2018-05-20',
                'celebrant_gender' => 'male',
                'subtotal_minor' => max(0, $totalMinor - 15000),
                'subtotal_currency' => 'EGP',
                'delivery_total_minor' => $totalMinor >= 100000 ? 15000 : 0,
                'delivery_total_currency' => 'EGP',
                'discount_total_minor' => 0,
                'discount_total_currency' => 'EGP',
                'loyalty_redeemed_minor' => 0,
                'loyalty_redeemed_currency' => 'EGP',
                'total_minor' => $totalMinor,
                'total_currency' => 'EGP',
                'amount_paid_minor' => $amountPaidMinor,
                'amount_paid_currency' => 'EGP',
                'submitted_at' => $lifecycle === LifecycleStatus::Draft ? null : '2026-05-15 09:00:00',
                'confirmed_at' => in_array($lifecycle, [LifecycleStatus::Active, LifecycleStatus::Completed], true) ? '2026-05-16 11:00:00' : null,
                'payment_hold_expires_at' => $payment === PaymentStatus::Unpaid ? '2026-05-19 08:30:00' : null,
            ]),
            ['reference_no' => $reference],
        );

        $this->updateOrCreateFactoryModel(
            BookingAddress::factory()->make([
                'booking_id' => $booking->id,
                'city_id' => $city->id,
                'address_line' => $reference === 'BK-DEV-1002'
                    ? '45 Lebanon Square, Mohandeseen'
                    : '12 Makram Ebeid Street, Nasr City',
                'building' => 'B',
                'floor' => '5',
                'apartment' => '51',
                'landmark' => 'Near main gate',
                'recipient_name' => $customer->name,
                'recipient_phone_e164' => $customer->phone_e164,
            ]),
            ['booking_id' => $booking->id],
        );

        return $booking;
    }

    private function seedBookingVendorWithItem(
        Booking $booking,
        VendorProfile $vendor,
        Service $service,
        VendorSubStatus $status,
        string $itemStatus,
        int $unitPriceMinor,
        int $deliveryFeeMinor,
        int $commissionBps,
        ProductType $productType,
    ): BookingVendor {
        $commissionMinor = (int) round($unitPriceMinor * $commissionBps / 10000);
        $vendorPayoutMinor = $unitPriceMinor - $commissionMinor + $deliveryFeeMinor;

        /** @var BookingVendor $bookingVendor */
        $bookingVendor = $this->updateOrCreateFactoryModel(
            BookingVendor::factory()->make([
                'public_id' => $this->stablePublicId('booking-vendor:'.$booking->reference_no.':'.$vendor->slug),
                'booking_id' => $booking->id,
                'vendor_profile_id' => $vendor->id,
                'sub_status' => $status,
                'response_deadline' => '2026-05-19 08:30:00',
                'responded_at' => $status === VendorSubStatus::Pending ? null : '2026-05-16 12:30:00',
                'vendor_notes' => ['en' => 'Development vendor note.', 'ar' => 'ملاحظة بائع تجريبية.'],
                'subtotal_minor' => $unitPriceMinor,
                'subtotal_currency' => 'EGP',
                'delivery_fee_minor' => $deliveryFeeMinor,
                'delivery_fee_currency' => 'EGP',
                'commission_minor' => $commissionMinor,
                'commission_currency' => 'EGP',
                'vendor_payout_minor' => $vendorPayoutMinor,
                'vendor_payout_currency' => 'EGP',
            ]),
            ['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id],
        );

        $this->updateOrCreateFactoryModel(
            BookingItem::factory()->make([
                'public_id' => $this->stablePublicId('booking-item:'.$booking->reference_no.':'.$service->slug),
                'booking_vendor_id' => $bookingVendor->id,
                'service_id' => $service->id,
                'product_type' => $productType,
                'name_snapshot' => $service->getTranslations('name'),
                'unit_price_minor' => $unitPriceMinor,
                'unit_price_currency' => 'EGP',
                'line_total_minor' => $unitPriceMinor,
                'line_total_currency' => 'EGP',
                'commission_minor' => $commissionMinor,
                'commission_currency' => 'EGP',
                'quantity' => 1,
                'effective_starts_at' => $productType === ProductType::Sale ? null : $booking->event_starts_at,
                'effective_ends_at' => $productType === ProductType::Rental ? $booking->event_ends_at : null,
                'customization_data' => ['seed' => true],
                'type_snapshot' => ['product_type' => $productType->value],
                'fulfillment_data' => ['status' => $itemStatus],
                'item_status' => $itemStatus,
                'commission_bps' => $commissionBps,
            ]),
            ['booking_vendor_id' => $bookingVendor->id, 'service_id' => $service->id],
        );

        return $bookingVendor;
    }

    private function seedModification(BookingVendor $bookingVendor): void
    {
        /** @var BookingModification $modification */
        $modification = $this->updateOrCreateFactoryModel(
            BookingModification::factory()->make([
                'public_id' => $this->stablePublicId('booking-modification:'.$bookingVendor->id.':change-price'),
                'booking_vendor_id' => $bookingVendor->id,
                'proposed_by' => VendorProfile::query()->findOrFail($bookingVendor->vendor_profile_id)->user_id,
                'proposal_kind' => ModificationProposalKind::ChangePrice,
                'status' => ModificationStatus::Pending,
                'expires_at' => '2026-05-19 09:30:00',
                'vendor_explanation' => ['en' => 'Upgrade to premium dessert table.', 'ar' => 'ترقية إلى طاولة حلويات مميزة.'],
                'diff_snapshot' => [
                    'before' => ['subtotal_minor' => 160000],
                    'after' => ['subtotal_minor' => 175000],
                ],
            ]),
            ['booking_vendor_id' => $bookingVendor->id, 'proposal_kind' => ModificationProposalKind::ChangePrice->value],
        );

        $targetItem = BookingItem::query()->where('booking_vendor_id', $bookingVendor->id)->firstOrFail();

        $this->firstOrCreateFactoryModel(
            BookingModificationItem::factory()->make([
                'booking_modification_id' => $modification->id,
                'target_booking_item_id' => $targetItem->id,
                'change_kind' => ModificationChangeKind::Update,
                'payload' => ['unit_price_minor' => 175000],
            ]),
            [
                'booking_modification_id' => $modification->id,
                'target_booking_item_id' => $targetItem->id,
                'change_kind' => ModificationChangeKind::Update->value,
            ],
        );
    }

    private function seedOpenPaymentLock(Booking $booking, User $customer): void
    {
        $this->firstOrCreateFactoryModel(
            BookingLock::factory()->make([
                'resource_type' => Booking::class,
                'resource_id' => $booking->id,
                'lock_token' => (string) Str::uuid(),
                'locked_by_user_id' => $customer->id,
                'lock_purpose' => 'payment',
                'acquired_at' => '2026-05-18 08:30:00',
                'expires_at' => '2026-05-19 08:30:00',
                'released_at' => null,
                'created_at' => '2026-05-18 08:30:00',
            ]),
            ['resource_type' => Booking::class, 'resource_id' => $booking->id, 'released_at' => null],
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function seedSnapshot(Booking $booking, int $version, string $triggerKind, array $snapshot): void
    {
        $this->firstOrCreateFactoryModel(
            BookingSnapshot::factory()->version($version)->make([
                'public_id' => $this->stablePublicId('booking-snapshot:'.$booking->reference_no.':'.$version),
                'booking_id' => $booking->id,
                'snapshot' => $snapshot,
                'trigger_kind' => $triggerKind,
                'created_at' => now()->subDays(5 - $version),
            ]),
            ['booking_id' => $booking->id, 'version' => $version],
        );
    }

    private function seedCustomerNote(Booking $booking, User $user, string $body, string $locale): void
    {
        $this->firstOrCreateFactoryModel(
            BookingCustomerNote::factory()->make([
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'body' => $body,
                'detected_locale' => $locale,
                'created_at' => now(),
            ]),
            ['booking_id' => $booking->id, 'user_id' => $user->id, 'body' => $body],
        );
    }

    private function seedTransition(Booking $booking, ?string $from, string $to, string $kind, string $createdAt): void
    {
        $this->firstOrCreateFactoryModel(
            StateTransition::factory()->make([
                'transitionable_type' => Booking::class,
                'transitionable_id' => $booking->id,
                'from_state' => $from,
                'to_state' => $to,
                'trigger_kind' => $kind,
                'context' => ['seed' => true],
                'created_at' => $createdAt,
            ]),
            [
                'transitionable_type' => Booking::class,
                'transitionable_id' => $booking->id,
                'to_state' => $to,
                'created_at' => $createdAt,
            ],
        );
    }
}
