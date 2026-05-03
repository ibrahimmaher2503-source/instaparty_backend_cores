<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\CreditWalletAction;
use App\Modules\Settlement\Application\Actions\DebitWalletAction;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────
// T700 — Wallet balance invariant:
//   wallets.balance_minor == SUM(wallet_ledger.amount_minor) for that wallet
// ─────────────────────────────────────────────────────

$ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

it('balance_minor equals sum of ledger entries after multiple credits', function () use ($ownerType): void {
    $credit = app(CreditWalletAction::class);

    $credit->execute($ownerType, 1, 50000, 'EGP', LedgerEntryType::CommissionCredit);
    $credit->execute($ownerType, 1, 30000, 'EGP', LedgerEntryType::CommissionCredit);
    $credit->execute($ownerType, 1, 20000, 'EGP', LedgerEntryType::CommissionCredit);

    $wallet = Wallet::where('owner_type', $ownerType)->where('owner_id', 1)->first();
    $ledgerSum = (int) WalletLedgerEntry::where('wallet_id', $wallet->id)->sum('amount_minor');

    expect($wallet->balance_minor)->toBe(100000)
        ->and($ledgerSum)->toBe(100000)
        ->and($wallet->balance_minor)->toBe($ledgerSum);
})->group('settlement', 'invariant', 'T700');

it('balance_minor equals sum of ledger entries after credits and a debit', function () use ($ownerType): void {
    $credit = app(CreditWalletAction::class);
    $debit = app(DebitWalletAction::class);

    $credit->execute($ownerType, 2, 80000, 'EGP', LedgerEntryType::CommissionCredit);
    $debit->execute($ownerType, 2, 20000, 'EGP', LedgerEntryType::WithdrawalDebit);
    $credit->execute($ownerType, 2, 10000, 'EGP', LedgerEntryType::CommissionCredit);

    $wallet = Wallet::where('owner_type', $ownerType)->where('owner_id', 2)->first();

    // Debit stores -20000 in ledger; credits store positive amounts.
    // Ledger sum: 80000 + (-20000) + 10000 = 70000
    // Wallet balance: 80000 - 20000 + 10000 = 70000
    $ledgerSum = (int) WalletLedgerEntry::where('wallet_id', $wallet->id)->sum('amount_minor');

    expect($wallet->balance_minor)->toBe(70000)
        ->and($ledgerSum)->toBe(70000)
        ->and($wallet->balance_minor)->toBe($ledgerSum);
})->group('settlement', 'invariant', 'T700');

it('balance_minor equals ledger sum after a refund debit drives balance negative', function () use ($ownerType): void {
    $credit = app(CreditWalletAction::class);
    $debit = app(DebitWalletAction::class);

    $credit->execute($ownerType, 3, 10000, 'EGP', LedgerEntryType::CommissionCredit);
    // Debit more than credited (simulates an out-of-order refund)
    // DebitWalletAction stores -30000 in ledger
    $debit->execute($ownerType, 3, 30000, 'EGP', LedgerEntryType::RefundDebit);

    $wallet = Wallet::where('owner_type', $ownerType)->where('owner_id', 3)->first();

    // Ledger: 10000 + (-30000) = -20000; wallet balance: 10000 - 30000 = -20000
    $ledgerSum = (int) WalletLedgerEntry::where('wallet_id', $wallet->id)->sum('amount_minor');

    expect($wallet->balance_minor)->toBe(-20000)
        ->and($ledgerSum)->toBe(-20000)
        ->and($wallet->balance_minor)->toBe($ledgerSum);
})->group('settlement', 'invariant', 'T700');

it('each wallet only reflects its own ledger entries', function () use ($ownerType): void {
    $credit = app(CreditWalletAction::class);

    $credit->execute($ownerType, 10, 50000, 'EGP', LedgerEntryType::CommissionCredit);
    $credit->execute($ownerType, 11, 90000, 'EGP', LedgerEntryType::CommissionCredit);

    $wallet10 = Wallet::where('owner_type', $ownerType)->where('owner_id', 10)->first();
    $wallet11 = Wallet::where('owner_type', $ownerType)->where('owner_id', 11)->first();

    $sum10 = (int) WalletLedgerEntry::where('wallet_id', $wallet10->id)->sum('amount_minor');
    $sum11 = (int) WalletLedgerEntry::where('wallet_id', $wallet11->id)->sum('amount_minor');

    expect($wallet10->balance_minor)->toBe($sum10)->toBe(50000)
        ->and($wallet11->balance_minor)->toBe($sum11)->toBe(90000);
})->group('settlement', 'invariant', 'T700');
