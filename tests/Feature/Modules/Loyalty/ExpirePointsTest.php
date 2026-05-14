<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('expires earn rows whose expires_at has passed', function (): void {
    $user = User::factory()->asCustomer()->create();
    $program = LoyaltyProgram::factory()->create();
    $earn = LoyaltyLedgerEntry::factory()->create([
        'user_id' => $user->id,
        'vendor_profile_id' => $program->vendor_profile_id,
        'loyalty_program_id' => $program->id,
        'direction' => LedgerDirection::Earn,
        'points' => 50,
        'balance_after' => 50,
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('loyalty:expire')->assertExitCode(0);

    expect(LoyaltyLedgerEntry::query()
        ->where('direction', LedgerDirection::Expire->value)
        ->where('reference_type', 'earn_expiry')
        ->where('reference_id', $earn->id)
        ->exists())->toBeTrue();
})->group('loyalty', 'feature');

it('leaves earn rows whose expires_at is in the future', function (): void {
    $user = User::factory()->asCustomer()->create();
    $program = LoyaltyProgram::factory()->create();
    LoyaltyLedgerEntry::factory()->create([
        'user_id' => $user->id,
        'vendor_profile_id' => $program->vendor_profile_id,
        'loyalty_program_id' => $program->id,
        'direction' => LedgerDirection::Earn,
        'points' => 50,
        'balance_after' => 50,
        'expires_at' => now()->addDays(10),
    ]);

    $this->artisan('loyalty:expire')->assertExitCode(0);

    expect(LoyaltyLedgerEntry::query()
        ->where('direction', LedgerDirection::Expire->value)
        ->exists())->toBeFalse();
})->group('loyalty', 'feature');

it('is idempotent across multiple runs', function (): void {
    $user = User::factory()->asCustomer()->create();
    $program = LoyaltyProgram::factory()->create();
    LoyaltyLedgerEntry::factory()->create([
        'user_id' => $user->id,
        'vendor_profile_id' => $program->vendor_profile_id,
        'loyalty_program_id' => $program->id,
        'direction' => LedgerDirection::Earn,
        'points' => 50,
        'balance_after' => 50,
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('loyalty:expire');
    $this->artisan('loyalty:expire');

    $expireRows = LoyaltyLedgerEntry::query()
        ->where('direction', LedgerDirection::Expire->value)
        ->count();
    expect($expireRows)->toBe(1);
})->group('loyalty', 'feature');
