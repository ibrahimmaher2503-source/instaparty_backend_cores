# Contract: Concurrency & Locks

**Module**: `app/Modules/Settlement/`
**Domain Contract**: `App\Modules\Settlement\Domain\Contracts\WalletLocker`
**Default implementation**: `App\Modules\Settlement\Infrastructure\Locks\RedisWalletLocker`

This contract is the only authorised way to serialise money-mutating operations against a wallet (or set of wallets).

## Interface

```php
namespace App\Modules\Settlement\Domain\Contracts;

interface WalletLocker
{
    /**
     * Try to acquire a Redis lock on the given wallet for $ttlSeconds.
     * Returns null on timeout. Returns a Lock handle that the caller MUST release
     * (or that will auto-release on script shutdown via Redis EX).
     *
     * The handle includes a randomly-generated token; release verifies the token
     * matches before deleting the key (Redlock-style guard against accidental
     * release by a slow caller after the lock expired).
     */
    public function tryAcquire(int $walletId, int $ttlSeconds, int $waitSeconds): ?WalletLockHandle;

    /**
     * Acquire locks for a set of wallets in deterministic ascending id order to
     * prevent deadlock. Returns null if ANY lock times out, after releasing all
     * acquired locks.
     *
     * @return list<WalletLockHandle>|null
     */
    public function tryAcquireMany(array $walletIds, int $ttlSeconds, int $waitSeconds): ?array;
}

interface WalletLockHandle
{
    public function release(): void;
    public function renew(int $additionalSeconds): bool;
    public function isStillHeld(): bool;
}
```

## Lock keys

| Purpose | Key pattern | Default TTL |
|---|---|---|
| Wallet mutation | `lock:wallet:{wallet_id}` | 30s |
| Reconciliation scope | `lock:reconcile:{scope_hash}` | 30 min |
| Settlement run | `lock:settle:{run_public_id}` | 60 min |
| Snapshot job per wallet | `lock:snapshot:{wallet_id}` | 5 min |

## Layered locking pattern

The canonical pattern used by every money-mutating Action:

```php
public function execute(...): SomeResult
{
    $lock = $this->walletLocker->tryAcquire($walletId, ttlSeconds: 30, waitSeconds: 5);
    if ($lock === null) {
        throw new LockAcquisitionTimeoutException($walletId);
    }

    try {
        return DB::transaction(function () use ($walletId) {
            $wallet = Wallet::where('id', $walletId)->lockForUpdate()->firstOrFail();
            // ... business logic ...
            // PostLedgerTransactionAction(...) writes inside same transaction.
            // ProjectWalletBalanceAction(...) updates cache inside same transaction.
            // DB::afterCommit(fn () => event(new WalletCredited(...))) for notifications.
        });
    } finally {
        $lock->release();
    }
}
```

**Order of acquisition**:
1. Redis lock first (cheap, kills duplicate runners before they touch DB).
2. DB `BEGIN`.
3. `SELECT ... FOR UPDATE` on the `wallets` row.
4. Business logic.
5. `COMMIT`.
6. Redis lock released in `finally`.

## Deadlock prevention

When an Action mutates more than one wallet in the same group (any double-entry transaction involving two non-suspense wallets, which is rare in Phase 1 but possible — e.g., a future vendor-to-vendor transfer), the locks are acquired via `tryAcquireMany` which sorts by `wallet_id ASC`. Any caller that breaks this ordering trips an architecture test that grep-scans for `WalletLocker::tryAcquire` patterns and flags multi-wallet uses outside `tryAcquireMany`.

## Reconciliation lock scope hash

```php
$scopeHash = md5(json_encode([
    'scope_type' => $input->scopeType,
    'scope_params' => $this->canonicaliseParams($input->scopeParams),
]));
```

This collapses semantically-equivalent scope inputs (e.g., the same date range expressed differently) into the same lock key, so a duplicate trigger does not get past the Redis layer.

## Settlement run lock

Acquired once per scheduled or manual `php artisan settle:run`. Held for the full duration of the batch. Within the batch, each withdrawal acquires its own wallet lock; the per-run lock prevents two operators from kicking off the same batch simultaneously.

## Tests

- `tests/Feature/Modules/Settlement/Withdrawals/ConcurrentRequestsBlockOverdrawTest.php` — forks N workers, asserts exactly 1 succeeds.
- `tests/Feature/Modules/Settlement/Reconciliation/ConcurrentRunIsSerialisedTest.php` — fires two reconciliation triggers with identical scope; asserts second one short-circuits.
- `tests/Feature/Modules/Settlement/LedgerWriter/ConcurrentCreditTest.php` — fires N credits at the same wallet; asserts final balance equals sum of inputs, ledger has N entries with correct running balances.
- `tests/Unit/Modules/Settlement/RedisWalletLockerTest.php` — exercises the handle lifecycle and the timeout path.

## Failure modes and recovery

| Failure | Behaviour |
|---|---|
| Redis is unavailable | Action raises `LockAcquisitionTimeoutException` immediately; caller treats as retryable. No silent fallthrough. |
| Lock held by crashed worker | TTL expires; next acquirer succeeds. The crashed worker's DB transaction has already rolled back. |
| DB row lock timeout | Action raises a `DeadlockException` (Laravel) → retried up to 3 times → finally fails as `LockAcquisitionTimeoutException`. |
| Worker crashes between Redis lock release and DB commit | Redis lock auto-expires (TTL); DB transaction rolls back (no commit); state is consistent. |
| Worker crashes after DB commit but before Redis lock release | Lock auto-expires (TTL); the committed state is correct. |
