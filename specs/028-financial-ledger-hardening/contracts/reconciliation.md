# Contract: Reconciliation

**Module**: `app/Modules/Settlement/`
**Domain Contracts**:
- `App\Modules\Settlement\Domain\Contracts\ReconciliationDetector`
- `App\Modules\Settlement\Application\Actions\RunReconciliationAction`
- `App\Modules\Settlement\Application\Actions\ReconcileWalletAction`

## RunReconciliationAction

Orchestrator. Acquires a Redis lock scoped to the run scope, creates a `reconciliation_runs` row in `running` status, dispatches per-wallet reconciliation in batches, aggregates results, and finalises the run.

```php
public function execute(RunReconciliationInput $input): ReconciliationRunResult;

final readonly class RunReconciliationInput
{
    public function __construct(
        public string $scopeType,           // 'all'|'wallet'|'vendor'|'date_range'|'recent_touch'
        public array $scopeParams,          // wallet_id, vendor_id, date_from, date_to, recent_touch_window
        public string $triggerKind,         // 'scheduled'|'manual'|'admin_endpoint'
        public ?int $triggeredByUserId,
        public ?string $idempotencyKey,
    ) {}
}

final readonly class ReconciliationRunResult
{
    public function __construct(
        public int $runId,
        public string $runPublicId,
        public string $status,
        public int $walletsScanned,
        public int $findingsCount,
        public int $autoRepairedCount,
        public int $manualReviewCount,
    ) {}
}
```

**Behaviour**:
- Concurrent calls with the same scope are blocked by the Redis lock — they return immediately with the in-progress run's public id rather than queuing a second run.
- The run is durable: a crash mid-flight leaves the row in `running` status with `started_at` set; a watchdog re-marks it `failed` after the lock TTL expires and the next scheduled run picks up where it left off.

## ReconcileWalletAction

Per-wallet logic. Idempotent; safe to call multiple times.

```php
public function execute(int $walletId, int $reconciliationRunId): ReconcileWalletResult;
```

**Steps**:
1. Acquire DB row lock on `wallets`.
2. Compute the projected balance from `wallet_ledger` directly (no cache read).
3. Compare to `wallets.balance_minor`. If different:
   - Insert `reconciliation_findings` with `finding_type='wallet_cache_drift'`, `severity='warning'`, expected/actual/delta JSON.
   - Auto-repair: re-project the cache from the ledger (calls `ProjectWalletBalanceAction`).
   - Mark the finding `resolution='auto_repaired'`.
4. Compute the projected `pending_withdrawal_minor` from `wallet_ledger` and `withdrawals` linkage. Compare. Same flow.
5. Check structural invariants (delegated to `ReconciliationDetector`):
   - Every `refunds` row with `status='succeeded'` has a `ledger_group_id`.
   - Every `commissions` row has an `accrual_ledger_entry_id`.
   - Every `withdrawals` row in `reserved` or later has a `reserved_ledger_entry_id`.
   - The wallet has no `wallet_ledger` row with NULL `transaction_group_id`.
   - The wallet's most recent ledger entry's `running_balance_minor` equals the recomputed sum.
6. For any structural anomaly, insert a finding with `severity='high'` and `resolution=NULL` (manual review required).

## ReconciliationDetector (pure)

Contract for stateless detector functions, each returning a list of `Finding` value objects. Implementations are pure — they read from `wallet_ledger`, `wallets`, and the linkage columns; they do not write.

```php
interface ReconciliationDetector
{
    /** @return list<DetectedFinding> */
    public function detectCacheDrift(int $walletId): array;
    public function detectOrphanedRefunds(int $walletId): array;
    public function detectOrphanedLedgerEntries(int $walletId): array;
    public function detectUnbalancedGroups(int $walletId): array;
    public function detectCommissionsWithoutSnapshot(int $walletId): array;
    public function detectWithdrawalsWithoutReserve(int $walletId): array;
    public function detectNegativeVendorBalances(int $walletId): array;
    public function detectCurrencyMismatches(int $walletId): array;
}
```

## Scheduling

Registered in `app/Console/Kernel.php` (or `routes/console.php` for Laravel 12):

```php
Schedule::command('reconcile:run --scope=recent_touch --window=60min')
    ->everyHour()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('reconcile:run --scope=all')
    ->dailyAt('04:00')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('ledger:snapshot --all')
    ->dailyAt('04:30')
    ->timezone('Africa/Cairo')
    ->onOneServer();
```

## Notification dispatch on findings

- `severity='high'` findings dispatch a `ReconciliationFindingRaised` event → queued listener → `DispatchNotificationAction` → admin role users.
- `severity='warning'` findings are silent (auto-repair logged only).
- `severity='info'` findings are silent.

Email/SMS templates live in `app/Modules/Settlement/Resources/lang/{en,ar}/settlement.php` and `notification_templates`.
