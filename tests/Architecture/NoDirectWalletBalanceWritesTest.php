<?php

declare(strict_types=1);

/**
 * T056 — Architecture guard: no code outside EloquentWalletRepository::applyProjection()
 * may write directly to wallet balance columns.
 *
 * Keyed on the @ledger-projection-write docblock annotation — any other file that
 * calls increment/decrement on wallet balance columns is a violation.
 */

it('no file outside EloquentWalletRepository calls incrementBalance or decrementBalance on wallets', function (): void {
    $repoPath = realpath(
        base_path('app/Modules/Settlement/Infrastructure/Repositories/EloquentWalletRepository.php')
    );

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $violations = [];

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getRealPath();

        if ($path === $repoPath) {
            continue;
        }

        $content = file_get_contents($path);

        // Detect raw DB::table('wallets')->increment/decrement on balance columns.
        if (preg_match('/DB::table\([\'"]wallets[\'"]\).*?(increment|decrement)\([\'"]balance_minor/s', $content)) {
            $violations[] = $path;
        }

        // Detect Wallet::where(...)->increment/decrement on balance columns.
        if (preg_match('/Wallet::(where|find|query).*?(increment|decrement)\([\'"]balance_minor/s', $content)) {
            $violations[] = $path;
        }

        // Detect raw DB::table('wallets') update with balance columns.
        if (preg_match('/DB::table\([\'"]wallets[\'"]\).*?->update\(.*?(balance_minor|pending_withdrawal_minor)/s', $content)) {
            $violations[] = $path;
        }

        // Detect Wallet static chain update with balance columns.
        if (preg_match('/Wallet::(where|find|query).*?->update\(.*?(balance_minor|pending_withdrawal_minor)/s', $content)) {
            $violations[] = $path;
        }

        // Detect instance-level increment/decrement on balance columns.
        if (preg_match('/->(increment|decrement)\([\'"](balance_minor|pending_withdrawal_minor)[\'"]/', $content)) {
            $violations[] = $path;
        }
    }

    expect($violations)
        ->toBeEmpty(
            "Direct wallet balance writes found outside EloquentWalletRepository:\n" .
            implode("\n", $violations)
        );
})->group('architecture', 'ledger');

it('EloquentWalletRepository has the @ledger-projection-write annotation on applyProjection', function (): void {
    $path    = base_path('app/Modules/Settlement/Infrastructure/Repositories/EloquentWalletRepository.php');
    $content = file_get_contents($path);

    expect($content)->toContain('@ledger-projection-write');
    expect($content)->toContain('applyProjection(');
})->group('architecture', 'ledger');

it('deprecated direct balance mutation methods throw BadMethodCallException when hardening flag is enabled', function (): void {
    config(['feature_flags.financial_ledger_hardening_v2' => true]);

    $repo    = new \App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository();
    $methods = ['incrementBalance', 'decrementBalance', 'incrementPendingWithdrawal', 'decrementPendingWithdrawal'];

    foreach ($methods as $method) {
        expect(fn () => $repo->{$method}(1, 100))
            ->toThrow(\BadMethodCallException::class);
    }
})->group('architecture', 'ledger');
