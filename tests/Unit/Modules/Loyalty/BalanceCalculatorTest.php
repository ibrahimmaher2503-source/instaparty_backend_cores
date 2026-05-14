<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('returns 0 when no ledger entries exist', function (): void {
    expect((new BalanceCalculator)->availableFor(1, 1))->toBe(0);
})->group('loyalty', 'unit');

it('returns the latest balance_after for (user, vendor)', function (): void {
    $user = User::factory()->asCustomer()->create();
    $program = LoyaltyProgram::factory()->create();
    $vendorId = (int) $program->vendor_profile_id;

    LoyaltyLedgerEntry::factory()->create([
        'user_id' => $user->id,
        'vendor_profile_id' => $vendorId,
        'loyalty_program_id' => $program->id,
        'direction' => LedgerDirection::Earn,
        'points' => 100,
        'balance_after' => 100,
    ]);
    LoyaltyLedgerEntry::factory()->create([
        'user_id' => $user->id,
        'vendor_profile_id' => $vendorId,
        'loyalty_program_id' => $program->id,
        'direction' => LedgerDirection::Redeem,
        'points' => 30,
        'balance_after' => 70,
    ]);

    expect((new BalanceCalculator)->availableFor((int) $user->id, $vendorId))->toBe(70);
})->group('loyalty', 'unit');
