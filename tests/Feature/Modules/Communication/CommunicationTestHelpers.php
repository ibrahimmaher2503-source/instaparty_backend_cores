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
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

if (! function_exists('makeAdminWithRole')) {
    function makeAdminWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }
}

if (! function_exists('makeRoutingRule')) {
    function makeRoutingRule(string $eventKey, AdminInboxSeverity $severity, int $roleId): AdminInboxRoutingRule
    {
        return AdminInboxRoutingRule::create([
            'public_id'         => Str::ulid()->toBase32(),
            'event_key'         => $eventKey,
            'severity'          => $severity->value,
            'route_to_role_id'  => $roleId,
            'route_to_admin_id' => null,
            'is_active'         => true,
        ]);
    }
}

if (! function_exists('createBookingForUser')) {
    function createBookingForUser(int $userId, string $productType, Carbon $createdAt): void
    {
        $occasion = Occasion::factory()->create();
        $vendor = VendorProfile::factory()->approved()->create();
        $category = Category::factory()->create();
        $service = Service::factory()->create([
            'product_type'      => ProductType::from($productType),
            'vendor_profile_id' => $vendor->id,
            'category_id'       => $category->id,
        ]);

        $booking = Booking::create([
            'public_id'                  => Str::ulid()->toBase32(),
            'customer_id'                => $userId,
            'occasion_id'                => $occasion->id,
            'lifecycle_status'           => LifecycleStatus::Confirmed,
            'payment_status'             => PaymentStatus::Paid,
            'fulfillment_status'         => FulfillmentStatus::NotStarted,
            'subtotal_currency'          => 'EGP',
            'delivery_total_currency'    => 'EGP',
            'discount_total_currency'    => 'EGP',
            'loyalty_redeemed_currency'  => 'EGP',
            'total_currency'             => 'EGP',
            'amount_paid_currency'       => 'EGP',
        ]);

        DB::table('bookings')->where('id', $booking->id)->update(['created_at' => $createdAt]);

        $bookingVendor = BookingVendor::create([
            'public_id'            => Str::ulid()->toBase32(),
            'booking_id'           => $booking->id,
            'vendor_profile_id'    => $vendor->id,
            'sub_status'           => VendorSubStatus::Accepted,
            'subtotal_minor'       => 0,
            'subtotal_currency'    => 'EGP',
            'delivery_fee_minor'   => 0,
            'delivery_fee_currency' => 'EGP',
            'commission_minor'     => 0,
            'commission_currency'  => 'EGP',
            'vendor_payout_minor'  => 0,
            'vendor_payout_currency' => 'EGP',
        ]);

        BookingItem::create([
            'public_id'           => Str::ulid()->toBase32(),
            'booking_vendor_id'   => $bookingVendor->id,
            'service_id'          => $service->id,
            'product_type'        => ProductType::from($productType),
            'name_snapshot'       => ['en' => 'Test Service', 'ar' => 'خدمة تجريبية'],
            'unit_price_minor'    => 10000,
            'unit_price_currency' => 'EGP',
            'line_total_minor'    => 10000,
            'line_total_currency' => 'EGP',
            'commission_minor'    => 0,
            'commission_currency' => 'EGP',
            'quantity'            => 1,
            'item_status'         => 'confirmed',
            'commission_bps'      => 0,
        ]);
    }
}
