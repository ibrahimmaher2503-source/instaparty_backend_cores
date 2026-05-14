<?php

declare(strict_types=1);

use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use App\Modules\Loyalty\Domain\Services\PointsCalculator;

it('returns floor(major * points_per_currency_unit) with no rule', function (): void {
    $program = new LoyaltyProgram(['points_per_currency_unit' => 1.0]);
    $calc = new PointsCalculator;

    expect($calc->earnPointsFor($program, 1000))->toBe(10);
})->group('loyalty', 'unit');

it('applies multiplier when a rule is supplied', function (): void {
    $program = new LoyaltyProgram(['points_per_currency_unit' => 1.0]);
    $rule = new LoyaltyRule(['multiplier' => 1.5]);
    $calc = new PointsCalculator;

    expect($calc->earnPointsFor($program, 1000, $rule))->toBe(15);
})->group('loyalty', 'unit');

it('returns base when rule is null', function (): void {
    $program = new LoyaltyProgram(['points_per_currency_unit' => 2.0]);

    expect((new PointsCalculator)->earnPointsFor($program, 1000, null))->toBe(20);
})->group('loyalty', 'unit');

it('returns 0 for negative or zero netMinor', function (): void {
    $program = new LoyaltyProgram(['points_per_currency_unit' => 1.0]);
    $calc = new PointsCalculator;

    expect($calc->earnPointsFor($program, 0))->toBe(0)
        ->and($calc->earnPointsFor($program, -500))->toBe(0);
})->group('loyalty', 'unit');
