<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Enums\RuleKind;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use App\Modules\Loyalty\Domain\Services\RuleResolver;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

/** @return array{LoyaltyProgram, User} */
function programAndUser(): array
{
    $vendor = VendorProfile::factory()->approved()->create();
    $program = LoyaltyProgram::factory()->create(['vendor_profile_id' => $vendor->id]);
    $user = User::factory()->asCustomer()->create();

    return [$program, $user];
}

it('returns the FirstBooking rule when no prior earn exists for (user, vendor)', function (): void {
    [$program, $user] = programAndUser();
    $rule = LoyaltyRule::factory()->create([
        'loyalty_program_id' => $program->id,
        'rule_kind' => RuleKind::FirstBooking,
        'is_active' => true,
    ]);

    $resolved = app(RuleResolver::class)->resolveForBookingItem($program, 1, $user->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($rule->id);
})->group('loyalty', 'unit');

it('returns null when a prior earn already exists for (user, vendor)', function (): void {
    [$program, $user] = programAndUser();
    LoyaltyRule::factory()->create([
        'loyalty_program_id' => $program->id,
        'rule_kind' => RuleKind::FirstBooking,
        'is_active' => true,
    ]);

    LoyaltyLedgerEntry::factory()->create([
        'user_id' => $user->id,
        'vendor_profile_id' => $program->vendor_profile_id,
        'loyalty_program_id' => $program->id,
        'direction' => LedgerDirection::Earn,
        'points' => 50,
        'balance_after' => 50,
    ]);

    $resolved = app(RuleResolver::class)->resolveForBookingItem($program, 1, $user->id);

    expect($resolved)->toBeNull();
})->group('loyalty', 'unit');

it('returns null when only Phase 2 rule kinds are configured', function (): void {
    [$program, $user] = programAndUser();
    LoyaltyRule::factory()->create([
        'loyalty_program_id' => $program->id,
        'rule_kind' => RuleKind::CategoryBonus,
        'is_active' => true,
        'conditions' => ['category_id' => 7],
    ]);
    LoyaltyRule::factory()->create([
        'loyalty_program_id' => $program->id,
        'rule_kind' => RuleKind::ThresholdBonus,
        'is_active' => true,
    ]);
    LoyaltyRule::factory()->create([
        'loyalty_program_id' => $program->id,
        'rule_kind' => RuleKind::Referral,
        'is_active' => true,
    ]);

    $resolved = app(RuleResolver::class)->resolveForBookingItem($program, 1, $user->id);

    expect($resolved)->toBeNull();
})->group('loyalty', 'unit');

it('prefers FirstBooking when mixed with ThresholdBonus and no prior earns exist', function (): void {
    [$program, $user] = programAndUser();
    LoyaltyRule::factory()->create([
        'loyalty_program_id' => $program->id,
        'rule_kind' => RuleKind::ThresholdBonus,
        'is_active' => true,
    ]);
    $firstBooking = LoyaltyRule::factory()->create([
        'loyalty_program_id' => $program->id,
        'rule_kind' => RuleKind::FirstBooking,
        'is_active' => true,
    ]);

    $resolved = app(RuleResolver::class)->resolveForBookingItem($program, 1, $user->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($firstBooking->id);
})->group('loyalty', 'unit');

it('skips inactive rules', function (): void {
    [$program, $user] = programAndUser();
    LoyaltyRule::factory()->create([
        'loyalty_program_id' => $program->id,
        'rule_kind' => RuleKind::FirstBooking,
        'is_active' => false,
    ]);

    $resolved = app(RuleResolver::class)->resolveForBookingItem($program, 1, $user->id);

    expect($resolved)->toBeNull();
})->group('loyalty', 'unit');

it('skips rules outside their starts_at/ends_at window', function (): void {
    [$program, $user] = programAndUser();
    LoyaltyRule::factory()->create([
        'loyalty_program_id' => $program->id,
        'rule_kind' => RuleKind::FirstBooking,
        'is_active' => true,
        'starts_at' => now()->addDays(7),
    ]);
    LoyaltyRule::factory()->create([
        'loyalty_program_id' => $program->id,
        'rule_kind' => RuleKind::FirstBooking,
        'is_active' => true,
        'ends_at' => now()->subDay(),
    ]);

    $resolved = app(RuleResolver::class)->resolveForBookingItem($program, 1, $user->id);

    expect($resolved)->toBeNull();
})->group('loyalty', 'unit');
