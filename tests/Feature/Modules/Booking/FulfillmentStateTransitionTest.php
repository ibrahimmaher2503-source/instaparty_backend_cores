<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\MarkBookingItemStateAction;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingStateTransition;
use App\Modules\Booking\Domain\States\DigitalItemStatus\PendingState as DigitalPending;
use App\Modules\Booking\Domain\States\DigitalItemStatus\RedeemedState;
use App\Modules\Booking\Domain\States\DigitalItemStatus\SentState;
use App\Modules\Booking\Domain\States\RentalItemStatus\DeliveredState as RentalDelivered;
use App\Modules\Booking\Domain\States\RentalItemStatus\OutForDeliveryState as RentalOutForDelivery;
use App\Modules\Booking\Domain\States\RentalItemStatus\PendingDeliveryState;
use App\Modules\Booking\Domain\States\RentalItemStatus\PickedUpState;
use App\Modules\Booking\Domain\States\RentalItemStatus\SetupCompleteState;
use App\Modules\Booking\Domain\States\RentalItemStatus\TeardownState;
use App\Modules\Booking\Domain\States\SaleItemStatus\DeliveredState as SaleDelivered;
use App\Modules\Booking\Domain\States\SaleItemStatus\InPreparationState;
use App\Modules\Booking\Domain\States\SaleItemStatus\OutForDeliveryState as SaleOutForDelivery;
use App\Modules\Booking\Domain\States\SaleItemStatus\PendingState as SalePending;
use App\Modules\Booking\Domain\States\SaleItemStatus\ReadyState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helper: override the item_status on a freshly-created booking item
// ─────────────────────────────────────────────────────────────────────────────

function makeItemForTransition(ProductType $type, string $fromStatus): array
{
    $data = makeSubmittedBookingWithVendor();
    $bv = $data['bookingVendor'];
    $vendor = $data['vendor'];

    // Accept the booking vendor to unlock transition paths
    $bv->update(['sub_status' => VendorSubStatus::Accepted]);

    // Force the item to the required starting status
    $item = $data['item'];
    $item->update(['product_type' => $type, 'item_status' => $fromStatus]);
    $item = $item->fresh();

    return compact('item', 'vendor');
}

// ─────────────────────────────────────────────────────────────────────────────
// Rental — 5 transitions
// ─────────────────────────────────────────────────────────────────────────────

it('transitions rental item: pending_delivery → out_for_delivery', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Rental, PendingDeliveryState::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, RentalOutForDelivery::class);

    expect($updated->item_status)->toBe(RentalOutForDelivery::$name);
})->group('booking', 'fulfillment', 'rental');

it('transitions rental item: out_for_delivery → delivered', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Rental, RentalOutForDelivery::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, RentalDelivered::class);

    expect($updated->item_status)->toBe(RentalDelivered::$name);
})->group('booking', 'fulfillment', 'rental');

it('transitions rental item: delivered → setup_complete', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Rental, RentalDelivered::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, SetupCompleteState::class);

    expect($updated->item_status)->toBe(SetupCompleteState::$name);
})->group('booking', 'fulfillment', 'rental');

it('transitions rental item: setup_complete → teardown', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Rental, SetupCompleteState::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, TeardownState::class);

    expect($updated->item_status)->toBe(TeardownState::$name);
})->group('booking', 'fulfillment', 'rental');

it('transitions rental item: teardown → picked_up', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Rental, TeardownState::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, PickedUpState::class);

    expect($updated->item_status)->toBe(PickedUpState::$name);
})->group('booking', 'fulfillment', 'rental');

// ─────────────────────────────────────────────────────────────────────────────
// Sale — 4 transitions
// ─────────────────────────────────────────────────────────────────────────────

it('transitions sale item: pending → in_preparation', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Sale, SalePending::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, InPreparationState::class);

    expect($updated->item_status)->toBe(InPreparationState::$name);
})->group('booking', 'fulfillment', 'sale');

it('transitions sale item: in_preparation → ready', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Sale, InPreparationState::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, ReadyState::class);

    expect($updated->item_status)->toBe(ReadyState::$name);
})->group('booking', 'fulfillment', 'sale');

it('transitions sale item: ready → out_for_delivery', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Sale, ReadyState::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, SaleOutForDelivery::class);

    expect($updated->item_status)->toBe(SaleOutForDelivery::$name);
})->group('booking', 'fulfillment', 'sale');

it('transitions sale item: out_for_delivery → delivered', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Sale, SaleOutForDelivery::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, SaleDelivered::class);

    expect($updated->item_status)->toBe(SaleDelivered::$name);
})->group('booking', 'fulfillment', 'sale');

// ─────────────────────────────────────────────────────────────────────────────
// Digital — 2 transitions
// ─────────────────────────────────────────────────────────────────────────────

it('transitions digital item: pending → sent', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Digital, DigitalPending::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, SentState::class);

    expect($updated->item_status)->toBe(SentState::$name);
})->group('booking', 'fulfillment', 'digital');

it('transitions digital item: sent → redeemed', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Digital, SentState::$name);

    $updated = app(MarkBookingItemStateAction::class)->execute($item, $vendor, RedeemedState::class);

    expect($updated->item_status)->toBe(RedeemedState::$name);
})->group('booking', 'fulfillment', 'digital');

// ─────────────────────────────────────────────────────────────────────────────
// State transition is logged
// ─────────────────────────────────────────────────────────────────────────────

it('logs a BookingStateTransition entry after transition', function (): void {
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Sale, SalePending::$name);

    app(MarkBookingItemStateAction::class)->execute($item, $vendor, InPreparationState::class);

    $log = BookingStateTransition::where('transitionable_id', $item->id)
        ->where('transitionable_type', BookingItem::class)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->from_state)->toBe(SalePending::$name);
    expect($log->to_state)->toBe(InPreparationState::$name);
    expect($log->trigger_kind)->toBe('vendor');
})->group('booking', 'fulfillment', 'audit');

// ─────────────────────────────────────────────────────────────────────────────
// Invalid transition — returns 409
// ─────────────────────────────────────────────────────────────────────────────

it('aborts with 409 when transition is not allowed', function (): void {
    // Trying to skip from pending_delivery directly to setup_complete (invalid)
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Rental, PendingDeliveryState::$name);

    expect(fn () => app(MarkBookingItemStateAction::class)->execute($item, $vendor, SetupCompleteState::class))
        ->toThrow(HttpException::class);
})->group('booking', 'fulfillment', 'invalid');

it('aborts with 409 when item is already in terminal state', function (): void {
    // Trying to re-transition from a terminal state
    ['item' => $item, 'vendor' => $vendor] = makeItemForTransition(ProductType::Rental, PickedUpState::$name);

    expect(fn () => app(MarkBookingItemStateAction::class)->execute($item, $vendor, TeardownState::class))
        ->toThrow(HttpException::class);
})->group('booking', 'fulfillment', 'invalid');

// ─────────────────────────────────────────────────────────────────────────────
// Ownership guard — 403 on wrong vendor
// ─────────────────────────────────────────────────────────────────────────────

it('aborts with 403 when a different vendor tries to transition the item', function (): void {
    ['item' => $item] = makeItemForTransition(ProductType::Sale, SalePending::$name);

    $otherVendor = VendorProfile::factory()->approved()->create();

    expect(fn () => app(MarkBookingItemStateAction::class)->execute($item, $otherVendor, InPreparationState::class))
        ->toThrow(HttpException::class);
})->group('booking', 'fulfillment', 'auth');
