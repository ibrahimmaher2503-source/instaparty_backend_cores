<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AddBookingModificationItemAction;
use App\Modules\Booking\Application\Actions\CreateBookingModificationAction;
use App\Modules\Booking\Application\DTOs\AddBookingModificationItemDTO;
use App\Modules\Booking\Application\DTOs\CreateBookingModificationDTO;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/../../BookingTestHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
});

function addItemSetup(): array
{
    $data = makeSubmittedBookingWithVendor();

    $draft = app(CreateBookingModificationAction::class)->execute(new CreateBookingModificationDTO(
        bookingVendorId: $data['bookingVendor']->id,
        vendorProfileId: $data['vendor']->id,
        proposedByUserId: $data['vendor']->user->id,
    ));

    $data['draft'] = $draft;

    return $data;
}

it('proposes a price change on an existing sale item', function (): void {
    $ctx = addItemSetup();

    $item = app(AddBookingModificationItemAction::class)->execute(new AddBookingModificationItemDTO(
        bookingModificationId: $ctx['draft']->id,
        vendorProfileId: $ctx['vendor']->id,
        proposedByUserId: $ctx['vendor']->user->id,
        changeKind: ModificationChangeKind::Update,
        changeType: ModificationProposalKind::ChangePrice,
        targetBookingItemId: $ctx['item']->id,
        payload: [
            'unit_price_minor' => 60000,
            'unit_price_currency' => 'EGP',
        ],
    ));

    expect($item->change_kind)->toBe(ModificationChangeKind::Update);
    expect($item->payload['change_type'])->toBe(ModificationProposalKind::ChangePrice->value);
    expect($item->payload['unit_price_minor'])->toBe(60000);
    expect($item->payload['price_delta_minor'])->toBe(10000);
    expect($item->payload['original_value']['unit_price_minor'])->toBe(50000);
})->group('booking', 'modification-builder', 'sale');

it('adds a brand-new rental line item', function (): void {
    $ctx = addItemSetup();
    $category = Category::factory()->create();
    $rentalService = Service::factory()->create([
        'product_type' => ProductType::Rental,
        'status' => PublishedState::class,
        'vendor_profile_id' => $ctx['vendor']->id,
        'category_id' => $category->id,
    ]);

    $item = app(AddBookingModificationItemAction::class)->execute(new AddBookingModificationItemDTO(
        bookingModificationId: $ctx['draft']->id,
        vendorProfileId: $ctx['vendor']->id,
        proposedByUserId: $ctx['vendor']->user->id,
        changeKind: ModificationChangeKind::Add,
        changeType: ModificationProposalKind::AddItem,
        targetBookingItemId: null,
        payload: [
            'service_id' => $rentalService->id,
            'product_type' => ProductType::Rental->value,
            'unit_price_minor' => 30000,
            'unit_price_currency' => 'EGP',
            'quantity' => 2,
            'effective_starts_at' => now()->addDays(30)->toIso8601String(),
            'effective_ends_at' => now()->addDays(30)->addHours(4)->toIso8601String(),
        ],
    ));

    expect($item->change_kind)->toBe(ModificationChangeKind::Add);
    expect($item->target_booking_item_id)->toBeNull();
    expect($item->payload['product_type'])->toBe(ProductType::Rental->value);
    expect($item->payload['price_delta_minor'])->toBe(60000);
    expect($item->payload['quantity_delta'])->toBe(2);
    expect($item->payload)->toHaveKey('effective_starts_at');
})->group('booking', 'modification-builder', 'rental');

it('adds a brand-new digital line item without slot fields', function (): void {
    $ctx = addItemSetup();
    $category = Category::factory()->create();
    $digitalService = Service::factory()->create([
        'product_type' => ProductType::Digital,
        'status' => PublishedState::class,
        'vendor_profile_id' => $ctx['vendor']->id,
        'category_id' => $category->id,
    ]);

    $item = app(AddBookingModificationItemAction::class)->execute(new AddBookingModificationItemDTO(
        bookingModificationId: $ctx['draft']->id,
        vendorProfileId: $ctx['vendor']->id,
        proposedByUserId: $ctx['vendor']->user->id,
        changeKind: ModificationChangeKind::Add,
        changeType: ModificationProposalKind::AddItem,
        targetBookingItemId: null,
        payload: [
            'service_id' => $digitalService->id,
            'product_type' => ProductType::Digital->value,
            'unit_price_minor' => 5000,
            'unit_price_currency' => 'EGP',
            'quantity' => 1,
            // intentionally pass slot fields — should be stripped by normalizer
            'effective_starts_at' => now()->toIso8601String(),
            'effective_ends_at' => now()->addHour()->toIso8601String(),
        ],
    ));

    expect($item->payload['product_type'])->toBe(ProductType::Digital->value);
    expect($item->payload['proposed_value'] ?? [])->not->toHaveKey('effective_starts_at');
    expect($item->payload['price_delta_minor'])->toBe(5000);
})->group('booking', 'modification-builder', 'digital');

it('auto-promotes quantity-to-zero update to a remove change', function (): void {
    $ctx = addItemSetup();

    $item = app(AddBookingModificationItemAction::class)->execute(new AddBookingModificationItemDTO(
        bookingModificationId: $ctx['draft']->id,
        vendorProfileId: $ctx['vendor']->id,
        proposedByUserId: $ctx['vendor']->user->id,
        changeKind: ModificationChangeKind::Update,
        changeType: ModificationProposalKind::ChangeQuantity,
        targetBookingItemId: $ctx['item']->id,
        payload: [
            'quantity' => 0,
        ],
    ));

    expect($item->change_kind)->toBe(ModificationChangeKind::Remove);
})->group('booking', 'modification-builder');

it('computes an ISO-8601 time_delta for rental slot changes', function (): void {
    $ctx = addItemSetup();
    $category = Category::factory()->create();
    $rentalService = Service::factory()->create([
        'product_type' => ProductType::Rental,
        'status' => PublishedState::class,
        'vendor_profile_id' => $ctx['vendor']->id,
        'category_id' => $category->id,
    ]);

    // Recreate the booking item as a rental to allow slot-edit testing
    $ctx['item']->update([
        'service_id' => $rentalService->id,
        'product_type' => ProductType::Rental,
        'effective_starts_at' => now()->addDays(30)->setTime(12, 0),
        'effective_ends_at' => now()->addDays(30)->setTime(16, 0),
    ]);

    $newStart = now()->addDays(30)->setTime(14, 0);
    $newEnd = now()->addDays(30)->setTime(18, 0);

    $item = app(AddBookingModificationItemAction::class)->execute(new AddBookingModificationItemDTO(
        bookingModificationId: $ctx['draft']->id,
        vendorProfileId: $ctx['vendor']->id,
        proposedByUserId: $ctx['vendor']->user->id,
        changeKind: ModificationChangeKind::Update,
        changeType: ModificationProposalKind::ChangeSlot,
        targetBookingItemId: $ctx['item']->id,
        payload: [
            'effective_starts_at' => $newStart->toIso8601String(),
            'effective_ends_at' => $newEnd->toIso8601String(),
        ],
    ));

    expect($item->payload['time_delta'])->not->toBeNull();
    expect($item->payload['time_delta'])->toContain('T2H');
})->group('booking', 'modification-builder', 'rental');
