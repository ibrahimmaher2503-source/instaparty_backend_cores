<?php

declare(strict_types=1);

use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Services\DiscountCalculator;

it('multiplies points by points_value_minor', function (): void {
    $program = new LoyaltyProgram(['points_value_minor' => 100]);
    expect((new DiscountCalculator)->discountMinorFor($program, 100))->toBe(10000);
})->group('loyalty', 'unit');

it('returns 0 for zero points', function (): void {
    $program = new LoyaltyProgram(['points_value_minor' => 100]);
    expect((new DiscountCalculator)->discountMinorFor($program, 0))->toBe(0);
})->group('loyalty', 'unit');

it('returns 0 for negative points', function (): void {
    $program = new LoyaltyProgram(['points_value_minor' => 100]);
    expect((new DiscountCalculator)->discountMinorFor($program, -10))->toBe(0);
})->group('loyalty', 'unit');
