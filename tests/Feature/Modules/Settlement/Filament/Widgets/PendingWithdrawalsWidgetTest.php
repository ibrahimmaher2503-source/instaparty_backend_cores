<?php

declare(strict_types=1);

use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\ApprovedState;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\PendingState;
use App\Modules\Settlement\Filament\Widgets\PendingWithdrawalsWidget;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    // Clear development-seeded withdrawal data so widget counts reflect only test data
    DB::table('withdrawals')->delete();
});

it('counts pending withdrawal', function (): void {
    Withdrawal::factory()->create(['status' => PendingState::class]);

    $widget = new PendingWithdrawalsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(1);
})->group('widgets', 'admin', 'settlement');

it('returns zero when withdrawal is approved', function (): void {
    Withdrawal::factory()->create(['status' => ApprovedState::class]);

    $widget = new PendingWithdrawalsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'settlement');
