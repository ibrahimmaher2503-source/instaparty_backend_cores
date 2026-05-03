<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

// ─────────────────────────────────────────────────────
// T513 — Pure unit tests for proportional reversal math
// These tests do NOT hit the database.
// ─────────────────────────────────────────────────────

/**
 * Mirrors the calculation in ReverseCommissionAction::execute().
 */
function calcReversal(int $refundAmountMinor, int $grossAmountMinor, int $vendorShareMinor): int
{
    $proportionDecimal = BigDecimal::of($refundAmountMinor)
        ->dividedBy($grossAmountMinor, 10, RoundingMode::HALF_EVEN);

    return (int) BigDecimal::of($vendorShareMinor)
        ->multipliedBy($proportionDecimal)
        ->toScale(0, RoundingMode::HALF_EVEN)
        ->toInt();
}

it('calculates 100% reversal exactly', function (): void {
    $reversal = calcReversal(100000, 100000, 85000);
    expect($reversal)->toBe(85000);
})->group('settlement', 'math');

it('calculates 50% reversal correctly', function (): void {
    $reversal = calcReversal(50000, 100000, 80000);
    expect($reversal)->toBe(40000);
})->group('settlement', 'math');

it('rounds 33.33% of 10000 with HALF_EVEN correctly', function (): void {
    // 33.33...% of 10 000 vendor_share
    $reversal = calcReversal(33333, 100000, 10000);
    // proportion = 0.33333, result ≈ 3333.3 → rounds to 3333
    expect($reversal)->toBe(3333);
})->group('settlement', 'math');

it('rounds 66.67% of 10000 with HALF_EVEN correctly', function (): void {
    // 66.67% of 10 000
    $reversal = calcReversal(66667, 100000, 10000);
    // proportion = 0.66667, result ≈ 6666.7 → rounds to 6667
    expect($reversal)->toBe(6667);
})->group('settlement', 'math');

it('33% + 67% reversals sum to the full vendor share', function (): void {
    $vendorShare = 10000;
    $gross = 10000;

    $first = calcReversal(3333, $gross, $vendorShare);   // ~3333
    $second = calcReversal(6667, $gross, $vendorShare);   // ~6667
    expect($first + $second)->toBe($vendorShare);
})->group('settlement', 'math');

it('calculates 1% reversal of a large amount correctly', function (): void {
    $reversal = calcReversal(10000, 1000000, 900000); // 1% of 900 000
    expect($reversal)->toBe(9000);
})->group('settlement', 'math');

it('calculates reversal when vendor share is 0 (100% platform fee)', function (): void {
    $reversal = calcReversal(100000, 100000, 0);
    expect($reversal)->toBe(0);
})->group('settlement', 'math');
