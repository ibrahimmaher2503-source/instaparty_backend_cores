<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Locks;

use App\Modules\Settlement\Domain\Contracts\WalletLockHandle;
use App\Modules\Settlement\Domain\Contracts\WalletLocker;
use Illuminate\Support\Facades\Cache;

class RedisWalletLocker implements WalletLocker
{
    public function tryAcquire(int $walletId, int $ttlSeconds, int $waitSeconds): ?WalletLockHandle
    {
        $lock = Cache::lock("wallet:{$walletId}:lock", $ttlSeconds);

        if (! $lock->block($waitSeconds)) {
            return null;
        }

        return new RedisWalletLockHandle($lock);
    }

    public function tryAcquireMany(array $walletIds, int $ttlSeconds, int $waitSeconds): ?array
    {
        // Ascending order to prevent deadlock across concurrent callers.
        sort($walletIds);

        $handles = [];

        foreach ($walletIds as $walletId) {
            $handle = $this->tryAcquire($walletId, $ttlSeconds, $waitSeconds);

            if ($handle === null) {
                foreach ($handles as $acquired) {
                    $acquired->release();
                }

                return null;
            }

            $handles[] = $handle;
        }

        return $handles;
    }
}
