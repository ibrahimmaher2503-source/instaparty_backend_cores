<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Feature Flags
|--------------------------------------------------------------------------
|
| Static feature flags for toggling behaviours before/during/after
| a staged rollout. These complement the DB-backed `feature_flags` table
| (runtime toggles changed by admin without deploy) but live here for
| flags whose values must be consistent across all workers at deploy time.
|
| --- Three-Stage Rollout for financial_ledger_hardening_v2 ---
|
| Stage 1 — SHADOW (default: flag = false)
|   Both the old direct-balance path AND the new ledger path run inside
|   the same DB transaction. `ledger:diff` is scheduled daily. If drift
|   is detected, the migration is halted and investigated before cut-over.
|   Duration: ≥ 1 week of zero-drift logs.
|
| Stage 2 — CUT-OVER (flag = true, deploy)
|   The new ledger path is the ONLY path. The old
|   `EloquentWalletRepository::incrementBalance / decrementBalance` throw
|   `BadMethodCallException`. `ledger:diff` still runs daily.
|   Rollback window: 24 h. If any production drift is found, flip back.
|
| Stage 3 — CLEANUP (flag = true, ≥ 30 days clean)
|   Remove deprecated balance-mutation helpers from the repository,
|   remove the shadow-write branch from every Action, delete this flag.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Financial Ledger Hardening v2 (Phase 4.9)
    |----------------------------------------------------------------------
    |
    | false — Stage 1 (shadow): both old + new paths run.
    | true  — Stage 2 (cut-over): new ledger path is canonical.
    |
    | Flip to `true` only after `php artisan ledger:diff` reports
    | "Zero drift" on staging for ≥ 7 consecutive days.
    |
    */
    'financial_ledger_hardening_v2' => (bool) env('FEATURE_LEDGER_HARDENING_V2', false),

];
