<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Application\Actions\ConfigureLoyaltyProgramAction;
use App\Modules\Loyalty\Application\DTOs\ProgramDraft;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function activeProgramDraft(): ProgramDraft
{
    return new ProgramDraft(
        name: ['en' => 'Test Program', 'ar' => 'برنامج اختبار'],
        terms: ['en' => 'Terms', 'ar' => 'الشروط'],
        isActive: true,
        pointsPerCurrencyUnit: 1.0,
        pointsValueMinor: 100,
        pointsValueCurrency: 'EGP',
        minPointsToRedeem: 100,
        maxRedeemPct: 50,
        pointsExpireAfterDays: 365,
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Configuration isolation — each vendor gets their own independent program
// ─────────────────────────────────────────────────────────────────────────────

it('two vendors can each have an independent active loyalty program', function (): void {
    $vendorA = VendorProfile::factory()->approved()->create();
    $vendorB = VendorProfile::factory()->approved()->create();

    app(ConfigureLoyaltyProgramAction::class)->execute($vendorA->id, activeProgramDraft());
    app(ConfigureLoyaltyProgramAction::class)->execute($vendorB->id, activeProgramDraft());

    $programA = LoyaltyProgram::query()->where('vendor_profile_id', $vendorA->id)->first();
    $programB = LoyaltyProgram::query()->where('vendor_profile_id', $vendorB->id)->first();

    expect($programA)->not->toBeNull();
    expect($programB)->not->toBeNull();
    expect($programA->id)->not->toBe($programB->id);
    expect($programA->is_active)->toBeTrue();
    expect($programB->is_active)->toBeTrue();
})->group('loyalty', 'isolation');

it('deactivating vendor A program does not deactivate vendor B program', function (): void {
    $vendorA = VendorProfile::factory()->approved()->create();
    $vendorB = VendorProfile::factory()->approved()->create();

    app(ConfigureLoyaltyProgramAction::class)->execute($vendorA->id, activeProgramDraft());
    app(ConfigureLoyaltyProgramAction::class)->execute($vendorB->id, activeProgramDraft());

    // Deactivate vendor A's program
    $inactiveDraft = new ProgramDraft(
        name: ['en' => 'Test Program', 'ar' => 'برنامج اختبار'],
        terms: ['en' => 'Terms', 'ar' => 'الشروط'],
        isActive: false,
        pointsPerCurrencyUnit: 1.0,
        pointsValueMinor: 100,
        pointsValueCurrency: 'EGP',
        minPointsToRedeem: 100,
        maxRedeemPct: 50,
        pointsExpireAfterDays: 365,
    );

    app(ConfigureLoyaltyProgramAction::class)->execute($vendorA->id, $inactiveDraft);

    $programA = LoyaltyProgram::query()->where('vendor_profile_id', $vendorA->id)->first();
    $programB = LoyaltyProgram::query()->where('vendor_profile_id', $vendorB->id)->first();

    expect($programA->is_active)->toBeFalse();
    expect($programB->is_active)->toBeTrue();
})->group('loyalty', 'isolation');

// ─────────────────────────────────────────────────────────────────────────────
// Balance isolation — customer earns separately per vendor
// ─────────────────────────────────────────────────────────────────────────────

it('customer loyalty balance for vendor A is isolated from vendor B', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $vendorA = VendorProfile::factory()->approved()->create();
    $vendorB = VendorProfile::factory()->approved()->create();

    $programA = LoyaltyProgram::factory()->active()->create(['vendor_profile_id' => $vendorA->id]);
    $programB = LoyaltyProgram::factory()->active()->create(['vendor_profile_id' => $vendorB->id]);

    // Earn 200 points from vendor A, 50 points from vendor B
    LoyaltyLedgerEntry::factory()->earn()->create([
        'user_id' => $customer->id,
        'vendor_profile_id' => $vendorA->id,
        'loyalty_program_id' => $programA->id,
        'points' => 200,
        'balance_after' => 200,
    ]);

    LoyaltyLedgerEntry::factory()->earn()->create([
        'user_id' => $customer->id,
        'vendor_profile_id' => $vendorB->id,
        'loyalty_program_id' => $programB->id,
        'points' => 50,
        'balance_after' => 50,
    ]);

    $balanceCalc = app(BalanceCalculator::class);

    expect($balanceCalc->availableFor($customer->id, $vendorA->id))->toBe(200);
    expect($balanceCalc->availableFor($customer->id, $vendorB->id))->toBe(50);
})->group('loyalty', 'isolation', 'balance');

// ─────────────────────────────────────────────────────────────────────────────
// Deactivation preserves existing balances
// ─────────────────────────────────────────────────────────────────────────────

it('deactivating a program preserves existing customer ledger entries', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();

    $program = LoyaltyProgram::factory()->active()->create(['vendor_profile_id' => $vendor->id]);

    LoyaltyLedgerEntry::factory()->earn()->create([
        'user_id' => $customer->id,
        'vendor_profile_id' => $vendor->id,
        'loyalty_program_id' => $program->id,
        'points' => 150,
        'balance_after' => 150,
    ]);

    // Deactivate the program
    $program->update(['is_active' => false]);

    // Ledger entries must survive deactivation
    $entryCount = LoyaltyLedgerEntry::query()
        ->where('user_id', $customer->id)
        ->where('vendor_profile_id', $vendor->id)
        ->count();

    expect($entryCount)->toBe(1);

    // Balance still reads correctly from the ledger
    $balance = app(BalanceCalculator::class)->availableFor($customer->id, $vendor->id);
    expect($balance)->toBe(150);
})->group('loyalty', 'isolation', 'deactivation');

it('deactivating a program does not delete loyalty rules', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();

    $program = LoyaltyProgram::factory()->active()->create(['vendor_profile_id' => $vendor->id]);

    // Deactivate the program
    $program->update(['is_active' => false]);

    // The program record itself still exists
    expect(LoyaltyProgram::query()->where('vendor_profile_id', $vendor->id)->exists())->toBeTrue();
    expect(LoyaltyProgram::query()->where('vendor_profile_id', $vendor->id)->first()->is_active)->toBeFalse();
})->group('loyalty', 'isolation', 'deactivation');
