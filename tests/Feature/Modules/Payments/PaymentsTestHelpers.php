<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus as BookingPaymentStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
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
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('seedPaymentsTestRoles')) {
    function seedPaymentsTestRoles(): void
    {
        static $seeded = false;
        if ($seeded) {
            return;
        }
        app(IdentityRolesSeeder::class)->run();
        Permission::findOrCreate('payment.refund', 'web');
        Permission::findOrCreate('view_refund', 'web');
        $seeded = true;
    }
}

if (! function_exists('resetPaymentsTestRoleCache')) {
    function resetPaymentsTestRoleCache(): void
    {
        // Spatie permission cache must be cleared after RefreshDatabase wipes the tables.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

if (! function_exists('makeConfirmedBookingWithItem')) {
    /**
     * Build a booking in `confirmed` state with one item of the requested product type.
     *
     * @return array{customer: User, booking: Booking, item: BookingItem, vendor: VendorProfile, service: Service}
     */
    function makeConfirmedBookingWithItem(
        ProductType $productType = ProductType::Sale,
        string $itemStatus = 'confirmed',
        ?Carbon $eventStartsAt = null,
    ): array {
        resetPaymentsTestRoleCache();
        if (Role::query()->where('name', 'customer')->doesntExist()) {
            app(IdentityRolesSeeder::class)->run();
            Permission::findOrCreate('payment.refund', 'web');
            Permission::findOrCreate('view_refund', 'web');
        }
        $customer = User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);
        $occasion = Occasion::factory()->create();
        $city = City::factory()->create();

        $booking = Booking::create([
            'public_id' => (string) Str::ulid(),
            'reference_no' => 'IP-PAY-'.rand(1000, 9999),
            'customer_id' => $customer->id,
            'occasion_id' => $occasion->id,
            'lifecycle_status' => LifecycleStatus::Confirmed,
            'payment_status' => BookingPaymentStatus::Unpaid,
            'fulfillment_status' => FulfillmentStatus::NotStarted,
            'event_starts_at' => $eventStartsAt ?? now()->addDays(30),
            'event_ends_at' => ($eventStartsAt ?? now()->addDays(30))->copy()->addHours(5),
            'subtotal_minor' => 50000,
            'subtotal_currency' => 'EGP',
            'delivery_total_currency' => 'EGP',
            'discount_total_currency' => 'EGP',
            'loyalty_redeemed_currency' => 'EGP',
            'total_minor' => 50000,
            'total_currency' => 'EGP',
            'amount_paid_currency' => 'EGP',
            'payment_hold_expires_at' => now()->addHours(24),
        ]);

        BookingAddress::create([
            'booking_id' => $booking->id,
            'city_id' => $city->id,
            'address_line' => '123 Test St',
            'recipient_name' => 'Test User',
            'recipient_phone_e164' => '+201234567890',
        ]);

        $vendor = VendorProfile::factory()->approved()->create();
        $category = Category::factory()->create();
        $service = Service::factory()->create([
            'product_type' => $productType,
            'status' => ServiceStatus::Published->value,
            'vendor_profile_id' => $vendor->id,
            'category_id' => $category->id,
        ]);

        $bookingVendor = BookingVendor::create([
            'public_id' => (string) Str::ulid(),
            'booking_id' => $booking->id,
            'vendor_profile_id' => $vendor->id,
            'sub_status' => VendorSubStatus::Accepted,
            'response_deadline' => now()->addHours(24),
            'subtotal_minor' => 50000,
            'subtotal_currency' => 'EGP',
            'delivery_fee_minor' => 0,
            'delivery_fee_currency' => 'EGP',
            'commission_minor' => 0,
            'commission_currency' => 'EGP',
            'vendor_payout_minor' => 50000,
            'vendor_payout_currency' => 'EGP',
        ]);

        $item = BookingItem::create([
            'public_id' => (string) Str::ulid(),
            'booking_vendor_id' => $bookingVendor->id,
            'service_id' => $service->id,
            'product_type' => $productType,
            'name_snapshot' => ['en' => 'Test Item', 'ar' => 'عنصر'],
            'unit_price_minor' => 50000,
            'unit_price_currency' => 'EGP',
            'line_total_minor' => 50000,
            'line_total_currency' => 'EGP',
            'commission_minor' => 0,
            'commission_currency' => 'EGP',
            'quantity' => 1,
            'item_status' => $itemStatus,
            'commission_bps' => 0,
            'effective_starts_at' => $eventStartsAt,
        ]);

        return compact('customer', 'booking', 'item', 'vendor', 'service');
    }
}

if (! function_exists('makeCapturedPayment')) {
    function makeCapturedPayment(Booking $booking, User $customer, int $amountMinor = 50000): Payment
    {
        return Payment::create([
            'public_id' => (string) Str::ulid(),
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway' => 'paymob',
            'gateway_ref' => 'PMB-'.Str::ulid(),
            'amount_minor' => $amountMinor,
            'amount_currency' => 'EGP',
            'method' => PaymentMethod::Card,
            'status' => PaymentStatus::Captured,
            'captured_at' => now(),
        ]);
    }
}

if (! function_exists('makeAdminUser')) {
    function makeAdminUser(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('payment.refund');

        return $admin;
    }
}
