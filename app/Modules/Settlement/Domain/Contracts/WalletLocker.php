<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Contracts;

interface WalletLocker
{
    /**
     * Try to acquire a Redis lock on the given wallet.
     *
     * Returns null on timeout (caller treats as retryable).
     * The returned handle MUST be released (or it auto-expires after $ttlSeconds).
     */
    public function tryAcquire(int $walletId, int $ttlSeconds, int $waitSeconds): ?WalletLockHandle;

    /**
     * Acquire locks for a set of wallets in deterministic ascending id order
     * to prevent deadlock.
     *
     * Returns null if ANY lock times out; all acquired locks are released first.
     *
     * @param  int[]  $walletIds
     * @return list<WalletLockHandle>|null
     */
    public function tryAcquireMany(array $walletIds, int $ttlSeconds, int $waitSeconds): ?array;
}
