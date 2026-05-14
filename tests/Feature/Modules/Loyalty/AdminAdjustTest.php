<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Application\Actions\AdjustLoyaltyBalanceAction;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function seedEarnEntry(int $userId, int $vendorProfileId, int $programId, int $points): LoyaltyLedgerEntry
{
    return LoyaltyLedgerEntry::factory()->create([
        'user_id' => $userId,
        'vendor_profile_id' => $vendorProfileId,
        'loyalty_program_id' => $programId,
        'direction' => LedgerDirection::Earn,
        'points' => $points,
        'balance_after' => $points,
    ]);
}

it('credits a positive admin adjustment to the ledger', function (): void {
    $user = User::factory()->asCustomer()->create();
    $admin = User::factory()->asAdmin()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $program = LoyaltyProgram::factory()->create(['vendor_profile_id' => $vendor->id]);

    $entry = app(AdjustLoyaltyBalanceAction::class)->execute(
        userId: $user->id,
        vendorProfileId: $vendor->id,
        pointsDelta: 250,
        reasonEn: 'Goodwill bonus',
        reasonAr: 'مكافأة حسن نية',
        adminUserId: $admin->id,
    );

    expect($entry->direction)->toBe(LedgerDirection::Adjust)
        ->and($entry->points)->toBe(250)
        ->and($entry->balance_after)->toBe(250)
        ->and($entry->reference_type)->toBe('admin_adjustment')
        ->and($entry->reference_id)->toBe($admin->id)
        ->and($entry->loyalty_program_id)->toBe($program->id);

    // Reason has both locales stored.
    $row = LoyaltyLedgerEntry::query()->where('id', $entry->id)->first();
    expect($row->getTranslation('reason', 'en'))->toBe('Goodwill bonus')
        ->and($row->getTranslation('reason', 'ar'))->toBe('مكافأة حسن نية');
})->group('loyalty', 'feature');

it('debits when delta is negative and balance permits', function (): void {
    $user = User::factory()->asCustomer()->create();
    $admin = User::factory()->asAdmin()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $program = LoyaltyProgram::factory()->create(['vendor_profile_id' => $vendor->id]);
    seedEarnEntry($user->id, $vendor->id, $program->id, 500);

    $entry = app(AdjustLoyaltyBalanceAction::class)->execute(
        userId: $user->id,
        vendorProfileId: $vendor->id,
        pointsDelta: -200,
        reasonEn: 'Correction',
        reasonAr: 'تصحيح',
        adminUserId: $admin->id,
    );

    expect($entry->direction)->toBe(LedgerDirection::Adjust)
        ->and($entry->points)->toBe(200) // magnitude
        ->and($entry->balance_after)->toBe(300);
})->group('loyalty', 'feature');

it('refuses to push the balance below zero', function (): void {
    $user = User::factory()->asCustomer()->create();
    $admin = User::factory()->asAdmin()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $program = LoyaltyProgram::factory()->create(['vendor_profile_id' => $vendor->id]);
    seedEarnEntry($user->id, $vendor->id, $program->id, 100);

    $action = app(AdjustLoyaltyBalanceAction::class);

    expect(fn () => $action->execute(
        userId: $user->id,
        vendorProfileId: $vendor->id,
        pointsDelta: -500,
        reasonEn: 'Bad',
        reasonAr: 'سيء',
        adminUserId: $admin->id,
    ))->toThrow(DomainException::class, 'loyalty.balance_underflow');

    // Balance untouched: only the original earn row remains.
    expect(LoyaltyLedgerEntry::query()->where('direction', LedgerDirection::Adjust->value)->count())->toBe(0);
})->group('loyalty', 'feature');
