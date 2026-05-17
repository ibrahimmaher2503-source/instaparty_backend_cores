<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Settlement\Application\Actions\CalculateCommissionAction;
use App\Modules\Settlement\Application\DTOs\BookingItemSnapshotDto;
use App\Modules\Settlement\Application\DTOs\PaymentSnapshotDto;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────
// Helper: build real FK-safe DTOs from seeded records
// ─────────────────────────────────────────────────────

/**
 * Create a PaymentSnapshotDto from a real Payment row.
 */
function settlementPaymentDtoFromReal(Payment $payment): PaymentSnapshotDto
{
    return new PaymentSnapshotDto(
        id: $payment->id,
        publicId: $payment->public_id,
        bookingId: $payment->booking_id,
        amountMinor: $payment->amount_minor,
        currency: $payment->amount_currency,
        status: PaymentStatus::Captured,
        capturedAt: Carbon::now(),
    );
}

/**
 * Create a BookingItemSnapshotDto from a real BookingItem row.
 * $bps overrides the commission_bps on the item (null → use resolver).
 */
function settlementItemDtoFromReal(
    BookingItem $item,
    ?int $bps = 1500,
): BookingItemSnapshotDto {
    $item->loadMissing('bookingVendor');

    return new BookingItemSnapshotDto(
        id: $item->id,
        publicId: $item->public_id,
        bookingId: $item->bookingVendor->booking_id,
        vendorProfileId: $item->bookingVendor->vendor_profile_id,
        categoryId: null,
        productType: $item->product_type,
        totalMinor: $item->line_total_minor,
        totalCurrency: $item->line_total_currency,
        commissionBps: $bps,
    );
}

// ─────────────────────────────────────────────────────
// T110 — Happy-path per product type
// ─────────────────────────────────────────────────────

it('calculates commission and credits vendor wallet for a rental item', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Rental);
    $payment = makeCapturedPayment($data['booking'], $data['customer']);

    $paymentDto = settlementPaymentDtoFromReal($payment);
    $itemDto = settlementItemDtoFromReal($data['item'], bps: 1500); // 15%

    $commission = app(CalculateCommissionAction::class)->execute($itemDto, $paymentDto);

    $gross = $data['item']->line_total_minor; // 50 000
    $expectedCom = (int) round($gross * 1500 / 10000); // 7 500
    $expectedShare = $gross - $expectedCom;            // 42 500

    expect($commission)->toBeInstanceOf(Commission::class)
        ->and($commission->status)->toBe(CommissionStatus::Calculated)
        ->and($commission->commission_bps)->toBe(1500)
        ->and($commission->commission_minor)->toBe($expectedCom)
        ->and($commission->vendor_share_minor)->toBe($expectedShare)
        ->and($commission->product_type)->toBe(ProductType::Rental);

    $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
    $wallet = Wallet::where('owner_type', $ownerType)
        ->where('owner_id', $data['vendor']->id)
        ->first();

    expect($wallet)->not->toBeNull()
        ->and($wallet->balance_minor)->toBe($expectedShare);

    // Phase 4.9: ledger entry is CommissionAccrual (credit to vendor wallet = vendor share)
    $ledger = WalletLedgerEntry::where('wallet_id', $wallet->id)
        ->where('direction', 'credit')
        ->first();
    expect($ledger->entry_type)->toBe(LedgerEntryType::CommissionAccrual)
        ->and($ledger->amount_minor)->toBe($expectedShare);
})->group('settlement', 'commission', 'rental');

it('calculates commission and credits vendor wallet for a sale item', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Sale);
    $payment = makeCapturedPayment($data['booking'], $data['customer']);

    $paymentDto = settlementPaymentDtoFromReal($payment);
    $itemDto = settlementItemDtoFromReal($data['item'], bps: 2000); // 20%

    $commission = app(CalculateCommissionAction::class)->execute($itemDto, $paymentDto);

    $gross = $data['item']->line_total_minor;
    $expectedCom = (int) round($gross * 2000 / 10000);
    $expectedShare = $gross - $expectedCom;

    expect($commission->commission_minor)->toBe($expectedCom)
        ->and($commission->vendor_share_minor)->toBe($expectedShare)
        ->and($commission->product_type)->toBe(ProductType::Sale);
})->group('settlement', 'commission', 'sale');

it('calculates commission and credits vendor wallet for a digital item', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Digital);
    $payment = makeCapturedPayment($data['booking'], $data['customer']);

    $paymentDto = settlementPaymentDtoFromReal($payment);
    $itemDto = settlementItemDtoFromReal($data['item'], bps: 500); // 5%

    $commission = app(CalculateCommissionAction::class)->execute($itemDto, $paymentDto);

    $gross = $data['item']->line_total_minor;
    $expectedCom = (int) round($gross * 500 / 10000);
    $expectedShare = $gross - $expectedCom;

    expect($commission->commission_minor)->toBe($expectedCom)
        ->and($commission->vendor_share_minor)->toBe($expectedShare)
        ->and($commission->product_type)->toBe(ProductType::Digital);
})->group('settlement', 'commission', 'digital');

// ─────────────────────────────────────────────────────
// T110 — Zero commission edge case (empty resolver table)
// ─────────────────────────────────────────────────────

it('creates a zero-commission record when bps is null and the resolver returns null', function (): void {
    // No commission_rates rows → resolver returns null → defaults to 0
    $data = makeConfirmedBookingWithItem(ProductType::Rental);
    $payment = makeCapturedPayment($data['booking'], $data['customer']);

    $paymentDto = settlementPaymentDtoFromReal($payment);
    $itemDto = settlementItemDtoFromReal($data['item'], bps: null);

    $commission = app(CalculateCommissionAction::class)->execute($itemDto, $paymentDto);

    expect($commission->commission_bps)->toBe(0)
        ->and($commission->commission_minor)->toBe(0)
        ->and($commission->vendor_share_minor)->toBe($data['item']->line_total_minor);
})->group('settlement', 'commission');

// ─────────────────────────────────────────────────────
// T113 — Idempotency: duplicate booking_item_id is blocked by UNIQUE index
// ─────────────────────────────────────────────────────

it('is idempotent — duplicate commission for same booking item returns replay without creating a second ledger group', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Rental);
    $payment = makeCapturedPayment($data['booking'], $data['customer']);

    $paymentDto = settlementPaymentDtoFromReal($payment);
    $itemDto = settlementItemDtoFromReal($data['item'], bps: 1500);

    $commissionsBefore = Commission::count();
    $groupsBefore = \DB::table('ledger_transaction_groups')->count();

    $action = app(CalculateCommissionAction::class);
    $action->execute($itemDto, $paymentDto); // First call succeeds

    // Second call returns idempotent replay — the new ledger writer deduplicates by idempotency key
    $action->execute($itemDto, $paymentDto);

    // Exactly one new commission and one new ledger group from this action (no duplication on retry)
    expect(Commission::count())->toBe($commissionsBefore + 1)
        ->and(\DB::table('ledger_transaction_groups')->count())->toBe($groupsBefore + 1);
})->group('settlement', 'commission', 'idempotency');
