<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Locks;

use App\Modules\Settlement\Domain\Contracts\WalletLockHandle;
use Illuminate\Cache\Lock;

class RedisWalletLockHandle implements WalletLockHandle
{
    private bool $released = false;

    public function __construct(private readonly Lock $lock) {}

    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->released = true;
        $this->lock->release();
    }

    public function renew(int $additionalSeconds): bool
    {
        // Laravel's base Lock class does not expose an extend/renew primitive.
        // Wallet operations are expected to complete well within the TTL; if renewal
        // is ever required in practice, use a RedisLock subclass with PEXPIRE.
        return false;
    }

    public function isStillHeld(): bool
    {
        if ($this->released) {
            return false;
        }

        // isOwnedByCurrentProcess() checks the token stored in Redis against our local token.
        return $this->lock->isOwnedByCurrentProcess();
    }
}
