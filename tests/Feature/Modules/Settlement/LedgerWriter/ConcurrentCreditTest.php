<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\CreditWalletAction;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('concurrent credits from N workers sum to the correct total and ledger has monotonically-increasing running balance', function (): void {
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped('pcntl required for concurrent fork test');
    }

    $vp = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();

    $walletRepo = app(EloquentWalletRepository::class);
    $wallet = $walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        $vp->id,
        'EGP',
    );

    $workerCount   = 5;
    $amountPerWorker = 10_000; // 100 EGP each

    $pids = [];
    for ($i = 0; $i < $workerCount; $i++) {
        $pid = pcntl_fork();
        if ($pid === 0) {
            // Child — boot the app and credit the wallet
            $app = require base_path('bootstrap/app.php');
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            try {
                $creditAction = $app->make(CreditWalletAction::class);
                $creditAction->execute(
                    walletId: $wallet->id,
                    amountMinor: $amountPerWorker,
                    currency: 'EGP',
                    idempotencyKey: "concurrent_credit_{$i}_{$vp->id}",
                    correlationId: (string) Str::ulid(),
                );
                exit(0);
            } catch (\Throwable) {
                exit(1);
            }
        }
        $pids[] = $pid;
    }

    $successCount = 0;
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $childStatus);
        if (pcntl_wifexited($childStatus) && pcntl_wexitstatus($childStatus) === 0) {
            $successCount++;
        }
    }

    expect($successCount)->toBe($workerCount);

    // Final balance equals sum of all credits
    $wallet->refresh();
    expect((int) $wallet->balance_minor)->toBe($workerCount * $amountPerWorker);

    // Ledger rows for this wallet are monotonically-increasing in running_balance_minor
    $ledgerRows = DB::table('wallet_ledger')
        ->where('wallet_id', $wallet->id)
        ->orderBy('id')
        ->pluck('running_balance_minor')
        ->toArray();

    expect($ledgerRows)->not()->toBeEmpty();

    $sorted = $ledgerRows;
    sort($sorted);
    expect($ledgerRows)->toEqual($sorted, 'running_balance_minor must be monotonically increasing');
})->group('concurrency', 'us3');
