# Phase 0 Research — Withdrawal Proof & Finance Audit

7 design decisions had to be resolved before writing migrations or Actions. Each is captured as Decision / Rationale / Alternatives.

---

## R-1. Should Approve and MarkPaid be one action or two?

**Decision**: Two distinct Action classes — `ApproveWithdrawalAction` and `MarkWithdrawalPaidAction`. The state machine adds an `Approved → Paid` transition and removes any direct `Pending → Paid` path.

**Rationale**:
- The spec (FR-001 / FR-EXT-205) requires explicit `approved` state before `paid` for audit separation.
- Two separate actions naturally produce two `audit_logs` rows with two distinct admin user IDs and timestamps — the whole point of the feature.
- The existing combined `ApproveAndMarkWithdrawalPaidAction` is only consumed by `WithdrawalsQueueResource` (verified via Grep). Splitting is safe.

**Alternatives considered**:
- *Keep the combined action and add `approved_at = paid_at` shortcut* — rejected because then `approved_by_admin_id` and `paid_by_admin_id` always equal each other, defeating the audit purpose.
- *Add a "draft → submitted → approved → paid" 4-state machine* — over-engineered for the actual use case; rejected.
- *Make MarkPaid take a withdrawal ID and look up the wallet itself, bypassing Approve* — rejected, breaks state-machine guard.

---

## R-2. Idempotency-key naming convention for the new MarkPaid action

**Decision**: Continue using `wd_settle:{withdrawal_id}` for the settle ledger group (unchanged from Phase 4.9), and additionally introduce `wd_approve:{withdrawal_id}` for the approve action's audit row.

**Rationale**:
- Phase 4.9 already uses `wd_settle:{id}` as the `idempotency_key` when posting the settle ledger group via `LedgerWriter`. Keeping that key means the ledger writer's existing idempotency guarantee carries over with zero new code.
- Adding `wd_approve:{id}` covers the case where an admin double-clicks Approve (rare but free to handle): the second click reads back the cached `audit_logs` insert via the `idempotency_keys` table and returns the same response.
- Both keys are scoped per-withdrawal (not per-admin), because we never want two approvals on the same withdrawal regardless of who clicked.

**Alternatives considered**:
- *No idempotency on Approve* — works fine technically (the state-machine guard rejects the second click), but produces a confusing 422 error in the admin UI on double-click. The idempotent replay produces a friendlier "already approved" success.
- *Per-(admin, withdrawal) keys* — rejected; this would allow a second admin to approve the same withdrawal, which the state-machine already prevents but is conceptually wrong.

---

## R-3. Column naming: split `processed_by_user_id` / `processed_at` vs new `approved_*` and `paid_*`

**Decision**: Keep the existing `processed_by_user_id` and `processed_at` columns in place (DO NOT drop them in this feature's migration). Add three new columns: `approved_at`, `approved_by_admin_id`, `paid_by_admin_id`. The migration backfills `approved_at = processed_at` and `approved_by_admin_id = processed_by_user_id` for historical `paid` rows; `paid_by_admin_id` is also backfilled from `processed_by_user_id` for historical `paid` rows. Future writes use ONLY the new columns; the `processed_*` columns are written for one more release window for backward compatibility and then dropped in a Phase 8 cleanup.

**Rationale**:
- Dropping `processed_*` in the same migration would break any external read consumer that selects those columns. Splitting the rename across two releases (write-both → read-new → drop-old) is the safe pattern.
- The new column names match the locked schema spec's intent (`approved_by`, `approved_at` already named in `11_DB_Schema.md §9`). Adding `_admin_id` suffix matches the project's convention (`approved_by_admin_id` is unambiguous).
- `paid_by_admin_id` distinct from `approved_by_admin_id` enables the dual-admin audit story.

**Alternatives considered**:
- *Drop `processed_*` in this migration* — rejected, see above (deferred to cleanup ticket).
- *Reuse `processed_by_user_id` for `approved_by_admin_id` and only add `paid_by_admin_id`* — rejected, less clear, and a future reader of the table would need a comment to disambiguate.

---

## R-4. `admin_payment_note` shape — translatable JSON vs single TEXT

**Decision**: JSON column with translatable EN/AR keys, mirroring `rejected_reason`. At least one of the two locales must be non-empty if the field is supplied; both may be set; either may be `null` independently.

**Rationale**:
- Constitution §IV mandates EN+AR for all user-facing text. The vendor sees this note in their wallet, so it must be locale-resolvable.
- `rejected_reason` already follows this exact pattern (JSON with `{en, ar}`) — consistency win.
- "At least one locale required" is enforced in the Form Request: if `admin_payment_note` is present, at least one of `admin_payment_note.en` or `admin_payment_note.ar` must be a non-empty string ≤ 1000 chars.

**Alternatives considered**:
- *Single TEXT column, locale-agnostic* — rejected, violates §IV.
- *Two separate columns (`admin_payment_note_en`, `admin_payment_note_ar`)* — rejected, violates the JSON-translatable rule in `02_Tech_Decisions.md §Translatable`.
- *Required in both locales* — rejected as too strict for an optional note; admins often only think in one language while doing operations.

---

## R-5. Vendor proof-download URL — signed-on-request vs stored

**Decision**: The vendor's `proof_download_url` is generated server-side on each request to `GET /api/v1/vendor/withdrawals/{public_id}` using Spatie Media Library's `getTemporaryUrl()` (or the S3 driver's `temporaryUrl()` if MinIO/local fallback), with a 15-minute TTL. The URL is NEVER persisted on the `withdrawals` row.

**Rationale**:
- Persisting a URL would either embed a permanent-public link (security violation) or embed a signed URL whose expiry would not auto-renew (broken UX).
- 15 minutes is long enough for a vendor to click and start the download, short enough that a leaked URL has bounded risk.
- Generating per-request is cheap (one S3 sign call) and is the standard pattern for private media.

**Alternatives considered**:
- *Streaming proxy endpoint (`GET /api/v1/vendor/withdrawals/{id}/proof`)* — works but adds an extra route + controller + auth check. Equivalent security guarantee. Reserved as a fallback if the storage driver doesn't support signed URLs.
- *24-hour TTL* — rejected, leaked-link blast radius too large.
- *1-minute TTL* — rejected, breaks browsers that pre-flight before downloading.

---

## R-6. Deprecation strategy for `ApproveAndMarkWithdrawalPaidAction`

**Decision**: Keep the class for ONE release window as a thin `@deprecated` wrapper that internally calls Approve then MarkPaid in sequence with a fabricated reference like `LEGACY-{public_id}` and a note `{en: "Legacy combined action — see ADR-0032", ar: "إجراء قديم مُجمَّع — راجع ADR-0032"}`. The Filament `WithdrawalsQueueResource` is migrated to use the two new actions immediately in this feature. A Phase 8 cleanup ticket deletes the wrapper once a `grep` confirms no consumers remain.

**Rationale**:
- Hard-delete in this PR would break any test, seeder, or service-provider binding that references the old class — preferring incremental change.
- The wrapper still respects the state-machine and posts the audit rows, so even legacy callers produce auditable history.
- The fabricated reference is intentionally ugly and prefixed `LEGACY-` so that a finance auditor immediately sees it came from the deprecated path and can chase down the offending caller.

**Alternatives considered**:
- *Delete immediately* — rejected; one release of overlap is cheap insurance.
- *Throw `BadMethodCallException` from the wrapper* — rejected; any silent caller (e.g., a scheduled job that nobody remembers wiring) would crash hard instead of degrading gracefully.

---

## R-7. ADR scope — new ADR vs amendment to ADR-0009 / ADR-0028

**Decision**: Write a new short ADR — **ADR-0032 — Withdrawal Two-Step Approve/Mark-Paid Split** — rather than amending ADR-0009 or ADR-0028. ADR-0032's "Related ADRs" section links back to both.

**Rationale**:
- ADR-0009 is the foundational Settlement module ADR — large, accepted, stable. Amending it for a behavior change pollutes the historical record.
- ADR-0028 is specifically about ledger hardening (Phase 4.9) — also stable.
- A focused ADR-0032 is the standard project pattern (see ADR-0030, ADR-0031 — both narrow scope, both accepted as new ADRs rather than amendments).
- ADR-0032 will also document the constitution §XI deviation (Spatie media for bank proof) and propose either an amendment or a Phase 8 cleanup — this gives a clean paper trail.

**Alternatives considered**:
- *Amend ADR-0009* — rejected, pollutes a stable foundational ADR.
- *No ADR (just plan.md)* — rejected, violates Constitution Principle VI (ADR before code) for any non-trivial behavior change.

---

## Resolution checklist

- [x] R-1: Split into two Actions
- [x] R-2: Idempotency key naming (`wd_approve:{id}`, `wd_settle:{id}`)
- [x] R-3: Additive migration; backfill `approved_*` and `paid_by_admin_id` from `processed_*`; defer drop of `processed_*` to Phase 8
- [x] R-4: `admin_payment_note` JSON translatable EN/AR
- [x] R-5: Signed proof URL, 15-min TTL, per-request
- [x] R-6: Deprecated wrapper for one release window
- [x] R-7: ADR-0032 new, narrow scope

All Phase 0 NEEDS CLARIFICATION items resolved. Proceed to Phase 1.
