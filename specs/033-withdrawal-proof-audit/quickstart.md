# Quickstart — Withdrawal Proof & Finance Audit

How to bring this feature up from a clean checkout, smoke-test it locally, and verify on staging.

## 1. Prerequisites

- On branch `033-withdrawal-proof-audit`
- Local MySQL + Redis up
- MinIO (or a `s3_private` disk equivalent) up; `STORAGE_S3_PRIVATE_*` env vars set
- Phase 4.2 + Phase 4.9 migrations already applied (check: `php artisan migrate:status` shows `2026_05_15_000013_alter_withdrawals_add_ledger_links` as `Ran`)

## 2. Bring-up

```bash
git status                                                  # clean? if not, stash unrelated work
php artisan migrate                                         # picks up the new alter migration
php artisan db:seed --class="App\\Modules\\Settlement\\Database\\Seeders\\SettlementPermissionsSeeder"
php artisan shield:generate --all                           # ensures new permissions are registered
php artisan filament:cache-components
php artisan optimize:clear
```

The migration is additive — no downtime, no row rewrites except the one-time backfill.

## 3. Smoke test (manual, ~5 minutes)

1. **Seed a vendor + wallet + paid bookings** (or rely on `php artisan db:seed --class=BookingDevelopmentSeeder` if already populated).
2. **As the vendor** (Sanctum token): `POST /api/v1/vendor/withdrawals` with `{ "amount_minor": 50000, "amount_currency": "EGP" }` and an `Idempotency-Key` header. Status `pending` returned.
3. **As an admin**: open `/admin/settlement-withdrawals`, click the pending row's `Approve` action. Confirm — toast appears. Reload — status is `approved`.
4. **As the same (or different) admin**: click `Mark Paid`. Fill `bank_transfer_reference = "EGTBNK-TEST-00001"`, upload `tests/fixtures/sample_proof.pdf`, add EN note `"smoke test"`. Submit — toast appears.
5. **As the vendor**: `GET /api/v1/vendor/withdrawals/{public_id}` — assert response contains the full timeline, the reference, the note, and a `proof_download_url` that downloads the PDF when followed.
6. **As a different vendor**: same GET → expect `404`.
7. **As the admin**: open `/admin/withdrawals/{public_id}` detail page — confirm the audit infolist shows both timestamps, both admin IDs, the reference, the EN/AR note panes, the inline proof preview, and a clickable link to the `withdrawal_settle` ledger transaction group.

## 4. Pest test run

```bash
./vendor/bin/pest tests/Feature/Modules/Settlement/WithdrawalApproveMarkPaidTest.php
```

Expect: 9 green tests, 0 skipped. Includes the dataset-driven bilingual cases.

Run the architecture invariant suite too — the append-only guard already lives in `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` and must remain green:

```bash
./vendor/bin/pest tests/Architecture/
```

## 5. Ledger reconciliation gate

After the test suite passes, run the Phase 4.9 ledger reconciliation command — it must return clean drift:

```bash
php artisan ledger:diff
```

Expected exit code 0, "0 wallets with drift".

## 6. Staging deploy verification

After merge + deploy:

1. Run the migration on staging: `php artisan migrate --force`.
2. Re-run seeder for permissions, run `shield:generate --all`.
3. Hit `/admin/settlement-withdrawals` — confirm the row actions are now `Approve` and `Mark Paid` (two distinct buttons) instead of the old combined `Approve & Mark Paid`.
4. Pick one historical `paid` withdrawal — confirm `approved_at` and `approved_by_admin_id` got backfilled (= `processed_at` / `processed_by_user_id`).
5. Run `php artisan ledger:diff` on staging — must be 0 drift.

## 7. Rollback plan

If the migration causes a problem:

```bash
php artisan migrate:rollback --step=1
```

The rollback drops the 5 new columns + the UNIQUE index. The `processed_*` columns are untouched, so the legacy combined action still works end-to-end (it just lacks the new audit separation).

The deprecated wrapper `ApproveAndMarkWithdrawalPaidAction` keeps working through rollback because it does not assume any of the new columns exist.

## 8. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `Class "MarkWithdrawalPaidAction" not found` after deploy | Composer autoload not refreshed | `composer dump-autoload` |
| Filament shows the old combined action only | Filament component cache stale | `php artisan filament:cache-components` |
| `withdrawal.approve` permission missing in role list | Shield not regenerated | `php artisan shield:generate --all` |
| `proof_download_url` returns 403 from the vendor | Disk visibility wrong or signed URL TTL elapsed | Verify `s3_private` disk has `visibility: private` and signed-URL generation is using `temporaryUrl()` with 15-min TTL |
| `Could not acquire lock on wallet N` exception on Mark Paid | Redis wallet lock contention from another process | Wait + retry; or check Phase 4.9 `WalletLocker` config |
| Audit log row missing | Action threw inside transaction → rolled back | Check application log; verify state-machine pre-condition |
| `php artisan ledger:diff` reports drift after a failed Mark Paid | Indicates the transaction did NOT roll back cleanly | Critical — open ADR-0028 §reconciliation procedure |

## 9. Where to look next

- `data-model.md` for column-level and Action-level details
- `contracts/` for Filament action contracts and the vendor API contract
- `research.md` for the seven design decisions
- `docs/adr/0032-withdrawal-two-step-approve-mark-paid.md` (to be written before any code lands) for the architectural rationale
