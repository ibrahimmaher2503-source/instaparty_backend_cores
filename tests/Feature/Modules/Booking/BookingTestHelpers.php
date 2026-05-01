<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Str;

if (! function_exists('makeSubmittedBookingWithVendor')) {
    function makeSubmittedBookingWithVendor(): array
    {
        $customer = User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);
        $occasion = Occasion::factory()->create();
        $city     = City::factory()->create();

        $booking = Booking::create([
            'public_id'                  => (string) Str::ulid(),
            'reference_no'               => 'IP-2026-V' . rand(1000, 9999),
            'customer_id'                => $customer->id,
            'occasion_id'                => $occasion->id,
            'lifecycle_status'           => LifecycleStatus::VendorReview,
            'payment_status'             => PaymentStatus::Unpaid,
            'fulfillment_status'         => FulfillmentStatus::NotStarted,
            'event_starts_at'            => now()->addDays(30),
            'event_ends_at'              => now()->addDays(30)->addHours(5),
            'subtotal_currency'          => 'EGP',
            'delivery_total_currency'    => 'EGP',
            'discount_total_currency'    => 'EGP',
            'loyalty_redeemed_currency'  => 'EGP',
            'total_currency'             => 'EGP',
            'amount_paid_currency'       => 'EGP',
        ]);

        \App\Modules\Booking\Domain\Models\BookingAddress::create([
            'booking_id'           => $booking->id,
            'city_id'              => $city->id,
            'address_line'         => '123 Test St',
            'recipient_name'       => 'Test User',
            'recipient_phone_e164' => '+201234567890',
        ]);

        $vendor   = VendorProfile::factory()->approved()->create();
        $category = Category::factory()->create();
        $service  = Service::factory()->create([
            'product_type'      => ProductType::Sale,
            'status'            => ServiceStatus::Published,
            'vendor_profile_id' => $vendor->id,
            'category_id'       => $category->id,
        ]);

        $bookingVendor = BookingVendor::create([
            'public_id'              => (string) Str::ulid(),
            'booking_id'             => $booking->id,
            'vendor_profile_id'      => $vendor->id,
            'sub_status'             => VendorSubStatus::Pending,
            'response_deadline'      => now()->addHours(24),
            'subtotal_minor'         => 50000,
            'subtotal_currency'      => 'EGP',
            'delivery_fee_minor'     => 0,
            'delivery_fee_currency'  => 'EGP',
            'commission_minor'       => 0,
            'commission_currency'    => 'EGP',
            'vendor_payout_minor'    => 50000,
            'vendor_payout_currency' => 'EGP',
        ]);

        $item = BookingItem::create([
            'public_id'           => (string) Str::ulid(),
            'booking_vendor_id'   => $bookingVendor->id,
            'service_id'          => $service->id,
            'product_type'        => ProductType::Sale,
            'name_snapshot'       => ['en' => 'Test Item', 'ar' => 'عنصر'],
            'unit_price_minor'    => 50000,
            'unit_price_currency' => 'EGP',
            'line_total_minor'    => 50000,
            'line_total_currency' => 'EGP',
            'commission_minor'    => 0,
            'commission_currency' => 'EGP',
            'quantity'            => 1,
            'item_status'         => 'pending',
            'commission_bps'      => 0,
        ]);

        return compact('customer', 'booking', 'vendor', 'bookingVendor', 'item', 'service');
    }
}

if (! function_exists('makeBookingWithPendingModification')) {
    function makeBookingWithPendingModification(): array
    {
        $data          = makeSubmittedBookingWithVendor();
        $customer      = $data['customer'];
        $booking       = $data['booking'];
        $vendor        = $data['vendor'];
        $bookingVendor = $data['bookingVendor'];
        $item          = $data['item'];

        // Simulate vendor modification: set booking to customer_review, vendor to modified
        $booking->update(['lifecycle_status' => \App\Modules\Booking\Domain\Enums\LifecycleStatus::CustomerReview]);
        $bookingVendor->update([
            'sub_status'   => \App\Modules\Booking\Domain\Enums\VendorSubStatus::Modified,
            'responded_at' => now(),
        ]);

        $modification = \App\Modules\Booking\Domain\Models\BookingModification::create([
            'public_id'         => (string) Str::ulid(),
            'booking_vendor_id' => $bookingVendor->id,
            'proposed_by'       => $vendor->user->id,
            'proposal_kind'     => \App\Modules\Booking\Domain\Enums\ModificationProposalKind::ChangePrice,
            'status'            => \App\Modules\Booking\Domain\Enums\ModificationStatus::Pending,
            'vendor_explanation' => ['en' => 'Price update', 'ar' => 'تحديث السعر'],
            'diff_snapshot'     => [
                'before' => ['items' => [], 'subtotal_minor' => 50000],
                'after'  => ['items' => [], 'subtotal_minor' => 60000],
            ],
        ]);

        \App\Modules\Booking\Domain\Models\BookingModificationItem::create([
            'booking_modification_id' => $modification->id,
            'target_booking_item_id'  => $item->id,
            'change_kind'             => \App\Modules\Booking\Domain\Enums\ModificationChangeKind::Update,
            'payload'                 => ['unit_price_minor' => 60000],
        ]);

        return compact('customer', 'booking', 'vendor', 'bookingVendor', 'item', 'modification');
    }
}
