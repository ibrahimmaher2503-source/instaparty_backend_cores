<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use Database\Seeders\IdentityRolesSeeder;

/**
 * Phase 3 D4 — loyalty history (15.2) + rules (15.3).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->customer = User::factory()->asCustomer()->create();
    $this->program = LoyaltyProgram::factory()->create();
});

it('lists the customer ledger history for one vendor only', function (): void {
    LoyaltyLedgerEntry::factory()->create([
        'user_id' => $this->customer->id,
        'vendor_profile_id' => $this->program->vendor_profile_id,
        'loyalty_program_id' => $this->program->id,
        'points' => 150,
    ]);

    // Noise: someone else's entry on the same program.
    LoyaltyLedgerEntry::factory()->create([
        'vendor_profile_id' => $this->program->vendor_profile_id,
        'loyalty_program_id' => $this->program->id,
    ]);

    $response = $this->actingAs($this->customer)
        ->getJson("/api/v1/customer/loyalty/programs/{$this->program->public_id}/history")
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.points'))->toBe(150)
        ->and($response->json('data.0'))->toHaveKeys(['public_id', 'direction', 'balance_after', 'created_at']);
})->group('loyalty');

it('exposes program parameters and active rules', function (): void {
    LoyaltyRule::factory()->create([
        'loyalty_program_id' => $this->program->id,
        'is_active' => true,
        'label' => ['en' => 'Double points weekend', 'ar' => 'نقاط مضاعفة'],
    ]);
    LoyaltyRule::factory()->create(['loyalty_program_id' => $this->program->id, 'is_active' => false]);

    $response = $this->actingAs($this->customer)
        ->getJson("/api/v1/customer/loyalty/programs/{$this->program->public_id}/rules")
        ->assertStatus(200);

    expect($response->json('data.program'))->toHaveKeys(['points_per_currency_unit', 'points_value_minor', 'min_points_to_redeem'])
        ->and($response->json('data.rules'))->toHaveCount(1)
        ->and($response->json('data.rules.0.label'))->toBe('Double points weekend');
})->group('loyalty');

it('requires authentication', function (): void {
    $this->getJson("/api/v1/customer/loyalty/programs/{$this->program->public_id}/history")
        ->assertStatus(401);
})->group('loyalty');
