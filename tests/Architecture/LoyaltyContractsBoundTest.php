<?php

declare(strict_types=1);

use App\Modules\Loyalty\Domain\Contracts\BookingDiscountWriter;
use App\Modules\Loyalty\Domain\Contracts\BookingDraftReader;
use App\Modules\Loyalty\Domain\Contracts\BookingItemNetAmountReader;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRuleRepository;
use App\Modules\Loyalty\Domain\Contracts\VendorLookup;

it('resolves every loyalty cross-module contract from the container', function (string $abstract): void {
    expect(fn () => app($abstract))->not->toThrow(\Throwable::class);
    expect(app($abstract))->toBeInstanceOf($abstract);
})
    ->with([
        [BookingItemNetAmountReader::class],
        [BookingDraftReader::class],
        [BookingDiscountWriter::class],
        [VendorLookup::class],
        [LoyaltyProgramRepository::class],
        [LoyaltyLedgerRepository::class],
        [LoyaltyRedemptionRepository::class],
        [LoyaltyRuleRepository::class],
    ])
    ->group('architecture', 'loyalty');
