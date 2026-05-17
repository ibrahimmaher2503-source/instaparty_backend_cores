<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\CreditWalletAction;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('10 concurrent withdrawal requests on a wallet with exactly 1x balance: exactly 1 succeeds', function (): void {
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped('pcntl required for concurrent fork test');
    }

    $vp = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();

    $walletRepo   = app(EloquentWalletRepository::class);
    $creditAction = app(CreditWalletAction::class);

    $wallet = $walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        $vp->id,
        'EGP',
    );

    $creditAction->execute(
        walletId: $wallet->id,
        amountMinor: 50_000,
        currency: 'EGP',
        idempotencyKey: 'concurrent_seed_' . $vp->id,
        correlationId: (string) Str::ulid(),
    );

    $user = $vp->user;

    // Fork 10 workers each requesting the full balance
    $pids = [];
    for ($i = 0; $i < 10; $i++) {
        $pid = pcntl_fork();
        if ($pid === 0) {
            // Child — boot the app and attempt the withdrawal
            $app = require base_path('bootstrap/app.php');
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            try {
                $action = $app->make(RequestWithdrawalAction::class);
                $action->execute(new RequestWithdrawalDto(
                    vendorProfileId: $vp->id,
                    amountMinor: 50_000,
                    currency: 'EGP',
                    bankAccount: validBankAccountPayload(),
                    requestedByUserId: $user->id,
                    idempotencyKey: 'concurrent_wd_' . $i . '_' . $vp->id,
                ));
                exit(0); // success
            } catch (\Throwable) {
                exit(1); // failure (overdraw or lock timeout)
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

    expect($successCount)->toBe(1);

    // Exactly one withdrawal_reserve ledger group
    expect(
        DB::table('ledger_transaction_groups')->where('kind', 'withdrawal_reserve')->count()
    )->toBe(1);

    // Wallet available balance is zero after the single success
    $wallet->refresh();
    $available = (int) $wallet->balance_minor - (int) $wallet->pending_withdrawal_minor;
    expect($available)->toBe(0);
})->group('concurrency', 'us3');
