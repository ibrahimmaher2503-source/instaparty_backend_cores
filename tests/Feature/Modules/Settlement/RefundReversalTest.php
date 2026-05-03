<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Settlement\Application\Actions\ReverseCommissionAction;
use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// Ensure the audit_logs table exists for the tests that exercise the negative-balance
// warning path. This table is part of the cross-cutting Shared module and may not have
// its migration published yet.
beforeEach(function (): void {
    if (! Schema::hasTable('audit_logs')) {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
});

// ─────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────

/**
 * Build a Commission row that is FK-safe, plus the corresponding Wallet.
 * Uses makeConfirmedBookingWithItem() + makeCapturedPayment() for real FK rows.
 */
function makeRealCommissionWithWallet(
    ProductType $type = ProductType::Rental,
    int $bps = 1500,
): array {
    $data = makeConfirmedBookingWithItem($type);
    $payment = makeCapturedPayment($data['booking'], $data['customer']);

    $gross = $data['item']->line_total_minor; // 50 000 from helper
    $commissionMinor = (int) round($gross * $bps / 10000);
    $vendorShareMinor = $gross - $commissionMinor;

    $commission = Commission::create([
        'booking_item_id' => $data['item']->id,
        'payment_id' => $payment->id,
        'vendor_profile_id' => $data['vendor']->id,
        'category_id' => null,
        'product_type' => $type,
        'gross_amount_minor' => $gross,
        'gross_amount_currency' => 'EGP',
        'commission_bps' => $bps,
        'commission_minor' => $commissionMinor,
        'commission_currency' => 'EGP',
        'vendor_share_minor' => $vendorShareMinor,
        'vendor_share_currency' => 'EGP',
        'reversed_amount_minor' => 0,
        'status' => CommissionStatus::Calculated,
    ]);

    $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
    $wallet = Wallet::factory()->withBalance($vendorShareMinor)->create([
        'owner_type' => $ownerType,
        'owner_id' => $data['vendor']->id,
    ]);

    // Represent the original commission credit in the ledger
    WalletLedgerEntry::factory()->commissionCredit()->create([
        'wallet_id' => $wallet->id,
        'amount_minor' => $vendorShareMinor,
    ]);

    return [$commission, $wallet, $data, $payment];
}

function makeTestRefundDto(int $refundId, int $paymentId, ?int $bookingItemId, int $amountMinor): RefundSnapshotDto
{
    return new RefundSnapshotDto(
        id: $refundId,
        publicId: 'REF-TEST-001',
        paymentId: $paymentId,
        bookingItemId: $bookingItemId,
        amountMinor: $amountMinor,
        currency: 'EGP',
        completedAt: Carbon::now(),
    );
}

// ─────────────────────────────────────────────────────
// T510 — Full refund reversal
// ─────────────────────────────────────────────────────

it('fully reverses a commission on a 100% refund and debits the wallet', function (): void {
    [$commission, $wallet, $data, $payment] = makeRealCommissionWithWallet(ProductType::Rental, 1500);

    // 100% refund amount equals gross
    $refund = makeTestRefundDto(
        refundId: 1,
        paymentId: $payment->id,
        bookingItemId: $data['item']->id,
        amountMinor: $commission->gross_amount_minor,
    );

    $updated = app(ReverseCommissionAction::class)->execute($commission, $refund);

    expect($updated->status)->toBe(CommissionStatus::Reversed)
        ->and($updated->reversed_amount_minor)->toBe($commission->vendor_share_minor);

    // Wallet was debited by vendor_share_minor
    $wallet->refresh();
    expect($wallet->balance_minor)->toBe(0);

    // Debit ledger entry created with a negative amount
    $debitEntry = WalletLedgerEntry::where('wallet_id', $wallet->id)
        ->where('entry_type', LedgerEntryType::RefundDebit->value)
        ->first();

    expect($debitEntry)->not->toBeNull()
        ->and($debitEntry->amount_minor)->toBe(-$commission->vendor_share_minor);
})->group('settlement', 'reversal', 'T510');

// ─────────────────────────────────────────────────────
// T511 — Partial refund
// ─────────────────────────────────────────────────────

it('partially reverses a commission on a 50% refund', function (): void {
    [$commission, $wallet, $data, $payment] = makeRealCommissionWithWallet(ProductType::Rental, 1500);

    $halfGross = (int) ($commission->gross_amount_minor / 2);

    $refund = makeTestRefundDto(
        refundId: 2,
        paymentId: $payment->id,
        bookingItemId: $data['item']->id,
        amountMinor: $halfGross,
    );

    $updated = app(ReverseCommissionAction::class)->execute($commission, $refund);

    expect($updated->status)->toBe(CommissionStatus::PartiallyReversed);

    // reversed amount is ~50% of vendor_share
    $expectedReversal = (int) round($commission->vendor_share_minor * 0.5);
    expect($updated->reversed_amount_minor)->toBe($expectedReversal);

    $wallet->refresh();
    expect($wallet->balance_minor)->toBe($commission->vendor_share_minor - $expectedReversal);
})->group('settlement', 'reversal', 'T511');

it('handles partial reversal for a sale item', function (): void {
    [$commission, $wallet, $data, $payment] = makeRealCommissionWithWallet(ProductType::Sale, 2000);

    $halfGross = (int) ($commission->gross_amount_minor / 2);

    $refund = makeTestRefundDto(3, $payment->id, $data['item']->id, $halfGross);
    $updated = app(ReverseCommissionAction::class)->execute($commission, $refund);

    expect($updated->status)->toBe(CommissionStatus::PartiallyReversed);
})->group('settlement', 'reversal', 'T511', 'sale');

it('handles full reversal for a digital item', function (): void {
    [$commission, $wallet, $data, $payment] = makeRealCommissionWithWallet(ProductType::Digital, 500);

    $refund = makeTestRefundDto(4, $payment->id, $data['item']->id, $commission->gross_amount_minor);
    $updated = app(ReverseCommissionAction::class)->execute($commission, $refund);

    expect($updated->status)->toBe(CommissionStatus::Reversed)
        ->and($updated->reversed_amount_minor)->toBe($commission->vendor_share_minor);
})->group('settlement', 'reversal', 'T511', 'digital');

// ─────────────────────────────────────────────────────
// T512 — Negative balance warning (audit log entry)
// ─────────────────────────────────────────────────────

it('creates an audit_logs entry when reversal causes a negative balance', function (): void {
    [$commission, $wallet, $data, $payment] = makeRealCommissionWithWallet(ProductType::Rental, 1500);

    // Force wallet balance to 0 so the debit will produce a negative balance
    $wallet->update(['balance_minor' => 0]);

    $refund = makeTestRefundDto(5, $payment->id, $data['item']->id, $commission->gross_amount_minor);
    app(ReverseCommissionAction::class)->execute($commission, $refund);

    $auditEntry = DB::table('audit_logs')
        ->where('action', 'negative_balance_warning')
        ->where('auditable_type', Commission::class)
        ->where('auditable_id', $commission->id)
        ->first();

    expect($auditEntry)->not->toBeNull();
})->group('settlement', 'reversal', 'T512');

// ─────────────────────────────────────────────────────
// T513 — Duplicate: debit applied only once
// ─────────────────────────────────────────────────────

it('does not double-debit when the reverse action is called once (debit count = 1)', function (): void {
    [$commission, $wallet, $data, $payment] = makeRealCommissionWithWallet(ProductType::Rental, 1000);

    $refund = makeTestRefundDto(6, $payment->id, $data['item']->id, $commission->gross_amount_minor);
    app(ReverseCommissionAction::class)->execute($commission, $refund);

    $debitCount = WalletLedgerEntry::where('wallet_id', $wallet->id)
        ->where('entry_type', LedgerEntryType::RefundDebit->value)
        ->count();

    expect($debitCount)->toBe(1);
})->group('settlement', 'reversal', 'T513', 'idempotency');
