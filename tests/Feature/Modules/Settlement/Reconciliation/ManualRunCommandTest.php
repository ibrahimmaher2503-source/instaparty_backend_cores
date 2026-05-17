<?php

declare(strict_types=1);

use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('artisan reconcile:run --scope=wallet produces a complete run row', function (): void {
    $vp         = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();
    $walletRepo = app(EloquentWalletRepository::class);

    $wallet = $walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        $vp->id,
        'EGP',
    );

    $this->artisan('reconcile:run', ['--scope' => 'wallet', '--wallet-id' => $wallet->id])
        ->assertExitCode(0);

    $run = ReconciliationRun::query()
        ->where('scope_type', 'wallet')
        ->latest('created_at')
        ->first();

    expect($run)->not()->toBeNull();
    expect($run->completed_at)->not()->toBeNull();
    expect($run->wallets_scanned)->toBe(1);
    expect($run->status->value)->not()->toBe('running');
})->group('us6');

it('artisan reconcile:run --scope=all completes without error on an empty database', function (): void {
    $this->artisan('reconcile:run', ['--scope' => 'all'])
        ->assertExitCode(0);

    $run = ReconciliationRun::query()->latest('created_at')->first();
    expect($run)->not()->toBeNull();
    expect($run->completed_at)->not()->toBeNull();
    expect($run->status->value)->toBe('clean');
})->group('us6');
