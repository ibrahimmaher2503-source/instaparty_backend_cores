<?php

declare(strict_types=1);

use App\Modules\Booking\Database\Seeders\BookingPermissionsSeeder;
use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingSnapshot;
use App\Modules\Shared\Domain\Models\StateTransition;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingResource;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingAddressesRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingItemsRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingSnapshotsRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingStateTransitionsRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingVendorsRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\PaymentsRelationManager;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Filament\Resources\PaymentResource;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(BookingPermissionsSeeder::class);
    $this->admin = User::factory()->asAdmin()->create();
});

function makeBookingViewFixture(User $admin): array
{
    $customer = User::factory()->asCustomer()->create([
        'name' => 'Mona Ahmed',
        'phone_e164' => '+201001234567',
        'preferred_locale' => 'ar',
    ]);

    $occasion = Occasion::factory()->create([
        'name' => [
            'en' => 'Birthday Party',
            'ar' => 'حفل عيد ميلاد',
        ],
    ]);

    $booking = Booking::factory()->submitted()->create([
        'customer_id' => $customer->id,
        'occasion_id' => $occasion->id,
        'lifecycle_status' => LifecycleStatus::VendorReview,
        'payment_status' => PaymentStatus::Unpaid,
        'fulfillment_status' => FulfillmentStatus::NotStarted,
        'subtotal_minor' => 150000,
        'subtotal_currency' => 'EGP',
        'delivery_total_minor' => 0,
        'delivery_total_currency' => 'EGP',
        'discount_total_minor' => 0,
        'discount_total_currency' => 'EGP',
        'loyalty_redeemed_minor' => 0,
        'loyalty_redeemed_currency' => 'EGP',
        'total_minor' => 150000,
        'total_currency' => 'EGP',
        'amount_paid_minor' => 50000,
        'amount_paid_currency' => 'EGP',
    ]);

    $city = City::factory()->create();

    BookingAddress::create([
        'booking_id' => $booking->id,
        'city_id' => $city->id,
        'address_line' => '12 Garden Street',
        'building' => 'Tower A',
        'floor' => '4',
        'apartment' => '18',
        'landmark' => 'Near the school',
        'recipient_name' => 'Mona Ahmed',
        'recipient_phone_e164' => '+201001234567',
    ]);

    $vendors = [];
    $items = [];

    foreach ([
        [ProductType::Rental, 'Elite Rentals'],
        [ProductType::Sale, 'Party Store'],
        [ProductType::Digital, 'Digital Gifts'],
    ] as [$productType, $name]) {
        $vendor = VendorProfile::factory()->approved()->create([
            'business_name' => [
                'en' => $name,
                'ar' => $name.' AR',
            ],
        ]);

        $serviceFactory = match ($productType) {
            ProductType::Rental => Service::factory()->published()->rental(),
            ProductType::Sale => Service::factory()->published()->sale(),
            ProductType::Digital => Service::factory()->published()->digital(),
        };

        $service = $serviceFactory->create([
            'vendor_profile_id' => $vendor->id,
            'name' => [
                'en' => $name.' Service',
                'ar' => $name.' Service AR',
            ],
        ]);

        $bookingVendor = BookingVendor::create([
            'booking_id' => $booking->id,
            'vendor_profile_id' => $vendor->id,
            'sub_status' => match ($productType) {
                ProductType::Rental => 'accepted',
                ProductType::Sale => 'pending',
                ProductType::Digital => 'completed',
            },
            'response_deadline' => now()->addDay(),
            'subtotal_minor' => 50000,
            'subtotal_currency' => 'EGP',
            'delivery_fee_minor' => 0,
            'delivery_fee_currency' => 'EGP',
            'commission_minor' => 5000,
            'commission_currency' => 'EGP',
            'vendor_payout_minor' => 45000,
            'vendor_payout_currency' => 'EGP',
        ]);

        $items[] = BookingItem::create([
            'booking_vendor_id' => $bookingVendor->id,
            'service_id' => $service->id,
            'product_type' => $productType,
            'name_snapshot' => [
                'en' => $name.' Snapshot',
                'ar' => $name.' Snapshot AR',
            ],
            'unit_price_minor' => 50000,
            'unit_price_currency' => 'EGP',
            'line_total_minor' => 50000,
            'line_total_currency' => 'EGP',
            'commission_minor' => 5000,
            'commission_currency' => 'EGP',
            'quantity' => 1,
            'effective_starts_at' => now()->addDays(2),
            'effective_ends_at' => now()->addDays(2)->addHours(3),
            'has_item_slot_override' => false,
            'customization_data' => [],
            'type_snapshot' => [],
            'fulfillment_data' => [],
            'item_status' => match ($productType) {
                ProductType::Rental => 'pending_delivery',
                ProductType::Sale => 'pending',
                ProductType::Digital => 'sent',
            },
            'commission_bps' => 1000,
        ]);

        $vendors[] = $bookingVendor;
    }

    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'user_id' => $customer->id,
        'amount_minor' => 50000,
        'amount_currency' => 'EGP',
    ]);

    $snapshot = BookingSnapshot::create([
        'booking_id' => $booking->id,
        'version' => 1,
        'snapshot' => [
            'lifecycle_status' => $booking->lifecycle_status->value,
            'payment_status' => $booking->payment_status->value,
            'fulfillment_status' => $booking->fulfillment_status->value,
        ],
        'trigger_kind' => 'booking_created',
        'trigger_reference_type' => Booking::class,
        'trigger_reference_id' => $booking->id,
        'triggered_by' => $admin->id,
        'created_at' => now(),
    ]);

    $transition = StateTransition::create([
        'transitionable_type' => Booking::class,
        'transitionable_id' => $booking->id,
        'from_state' => LifecycleStatus::Draft->value,
        'to_state' => LifecycleStatus::VendorReview->value,
        'triggered_by' => $admin->id,
        'trigger_kind' => 'admin',
        'context' => ['source' => 'admin-panel'],
        'created_at' => now(),
    ]);

    return compact(
        'customer',
        'occasion',
        'booking',
        'vendors',
        'items',
        'payment',
        'snapshot',
        'transition',
    );
}

it('opens the booking view page from the list', function (): void {
    $fixture = makeBookingViewFixture($this->admin);

    $this->actingAs($this->admin)
        ->get('/admin/bookings')
        ->assertSuccessful()
        ->assertSee($fixture['customer']->name)
        ->assertSee($fixture['customer']->phone_e164)
        ->assertSee($fixture['booking']->reference_no);

    $this->actingAs($this->admin)
        ->get("/admin/bookings/{$fixture['booking']->public_id}")
        ->assertSuccessful()
        ->assertSee(__('booking.relations.vendors'))
        ->assertSee(__('booking.relations.payments'));
})->group('booking', 'filament', 'us1', 'us3');

it('registers all six relation managers on the booking view', function (): void {
    expect(BookingResource::getRelations())->toBe([
        BookingVendorsRelationManager::class,
        BookingItemsRelationManager::class,
        BookingAddressesRelationManager::class,
        PaymentsRelationManager::class,
        BookingSnapshotsRelationManager::class,
        BookingStateTransitionsRelationManager::class,
    ]);
})->group('booking', 'filament', 'us1');

it('renders booking view safely when relation sections are empty', function (): void {
    $customer = User::factory()->asCustomer()->create([
        'name' => 'No Relations Customer',
        'phone_e164' => '+201009998887',
    ]);

    $occasion = Occasion::factory()->create([
        'name' => [
            'en' => 'No Relations Occasion',
            'ar' => 'Ù…Ù†Ø§Ø³Ø¨Ø© Ø¨Ù„Ø§ Ø¨ÙŠØ§Ù†Ø§Øª',
        ],
    ]);

    $booking = Booking::factory()->submitted()->create([
        'customer_id' => $customer->id,
        'occasion_id' => $occasion->id,
    ]);

    $baseUrl = "/admin/bookings/{$booking->public_id}";

    collect([
        0 => __('booking.empty_states.vendors'),
        1 => __('booking.empty_states.items'),
        2 => __('booking.empty_states.addresses'),
        3 => __('booking.empty_states.payments'),
        4 => __('booking.empty_states.snapshots'),
        5 => __('booking.empty_states.state_transitions'),
    ])->each(function (string $emptyStateText, int $index) use ($baseUrl): void {
        $this->actingAs($this->admin)
            ->get($baseUrl."?activeRelationManager={$index}")
            ->assertSuccessful()
            ->assertSee($emptyStateText);
    });
})->group('booking', 'filament', 'us1');

it('renders human-readable customer and occasion labels in the booking list', function (): void {
    $fixture = makeBookingViewFixture($this->admin);

    app()->setLocale('ar');

    $this->actingAs($this->admin)
        ->get('/admin/bookings')
        ->assertSuccessful()
        ->assertSee($fixture['customer']->name)
        ->assertSee($fixture['customer']->phone_e164)
        ->assertSee($fixture['occasion']->getTranslation('name', 'ar'));
})->group('booking', 'filament', 'us3');

it('renders booking relation rows with vendor, service, payment, snapshot, and transition data', function (): void {
    $fixture = makeBookingViewFixture($this->admin);

    $bookingViewBaseUrl = "/admin/bookings/{$fixture['booking']->public_id}";

    $this->actingAs($this->admin)
        ->get($bookingViewBaseUrl)
        ->assertSuccessful()
        ->assertSee($fixture['vendors'][0]->vendor->getTranslation('business_name', 'en'));

    $this->actingAs($this->admin)
        ->get($bookingViewBaseUrl.'?activeRelationManager=1')
        ->assertSuccessful()
        ->assertSee($fixture['items'][0]->service->getTranslation('name', 'en'));

    $this->actingAs($this->admin)
        ->get($bookingViewBaseUrl.'?activeRelationManager=3')
        ->assertSuccessful()
        ->assertSee($fixture['payment']->public_id)
        ->assertSee(PaymentResource::getUrl('view', ['record' => $fixture['payment']]));

    $this->actingAs($this->admin)
        ->get($bookingViewBaseUrl.'?activeRelationManager=4')
        ->assertSuccessful()
        ->assertSee($fixture['snapshot']->trigger_kind);

    $this->actingAs($this->admin)
        ->get($bookingViewBaseUrl.'?activeRelationManager=5')
        ->assertSuccessful()
        ->assertSee('Vendor Review');
})->group('booking', 'filament', 'us2');

it('keeps addresses, payments, snapshots, and state transitions relation managers read-only', function (): void {
    $fixture = makeBookingViewFixture($this->admin);
    $booking = $fixture['booking'];

    Livewire::test(BookingAddressesRelationManager::class, [
        'ownerRecord' => $booking,
        'pageClass' => ViewBooking::class,
    ])
        ->assertSuccessful()
        ->assertTableHeaderActionsExistInOrder([])
        ->assertTableActionsExistInOrder([])
        ->assertTableBulkActionsExistInOrder([]);

    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $booking,
        'pageClass' => ViewBooking::class,
    ])
        ->assertSuccessful()
        ->assertTableHeaderActionsExistInOrder([])
        ->assertTableActionsExistInOrder([])
        ->assertTableBulkActionsExistInOrder([]);

    Livewire::test(BookingSnapshotsRelationManager::class, [
        'ownerRecord' => $booking,
        'pageClass' => ViewBooking::class,
    ])
        ->assertSuccessful()
        ->assertTableHeaderActionsExistInOrder([])
        ->assertTableActionsExistInOrder([])
        ->assertTableBulkActionsExistInOrder([]);

    Livewire::test(BookingStateTransitionsRelationManager::class, [
        'ownerRecord' => $booking,
        'pageClass' => ViewBooking::class,
    ])
        ->assertSuccessful()
        ->assertTableHeaderActionsExistInOrder([])
        ->assertTableActionsExistInOrder([])
        ->assertTableBulkActionsExistInOrder([]);
})->group('booking', 'filament', 'us2');

it('keeps booking list and view queries bounded by eager loading', function (): void {
    $fixture = makeBookingViewFixture($this->admin);

    DB::flushQueryLog();
    DB::enableQueryLog();

    BookingResource::getEloquentQuery()
        ->limit(1)
        ->get();

    $listQueries = count(DB::getQueryLog());
    expect($listQueries)->toBeLessThanOrEqual(6);

    DB::flushQueryLog();

    Livewire::test(ViewBooking::class, ['record' => $fixture['booking']->public_id]);

    $viewQueries = count(DB::getQueryLog());
    expect($viewQueries)->toBeLessThanOrEqual(20);
})->group('booking', 'filament', 'us4');
