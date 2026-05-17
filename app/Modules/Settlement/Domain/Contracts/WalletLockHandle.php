<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Contracts;

interface WalletLockHandle
{
    /** Release the lock immediately (idempotent). */
    public function release(): void;

    /** Extend the lock TTL by $additionalSeconds. Returns false if the lock has already expired. */
    public function renew(int $additionalSeconds): bool;

    /** True if the lock token is still held in Redis. */
    public function isStillHeld(): bool;
}
