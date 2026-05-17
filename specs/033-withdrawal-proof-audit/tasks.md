---
description: "Task list for 033-withdrawal-proof-audit"
---

# Tasks: Withdrawal Proof & Finance Audit

**Input**: Design documents from `specs/033-withdrawal-proof-audit/`
**Prerequisites**: plan.md, spec.md (both required) — plus research.md, data-model.md, contracts/, quickstart.md (all present)
**Branch**: `033-withdrawal-proof-audit`

**Tests**: INCLUDED — required by spec.md FR-018 (9 enumerated cases) and constitution §VII (money flows are test-first / same-day).

**Organization**: Tasks grouped by user story (US1, US2, US3) per spec.md priorities. Setup + Foundational phases are shared. Polish phase covers cross-cutting docs and registry updates.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story (US1, US2, US3)
- Every task names exact file path(s)

## Path Convention

Brownfield Laravel modular monolith. All paths relative to repo root `C:\instaparty_backend_cores\`.

- Module code: `app/Modules/Settlement/`
- Migrations: `app/Modules/Settlement/Database/Migrations/`
- Tests: `tests/Feature/Modules/Settlement/`
- Spec docs: `specs/033-withdrawal-proof-audit/`
- Project docs: `docs/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: ADR + migration + permission seed — the foundation every story depends on.

- [X] T001 Author ADR-0032 at `docs/adr/0032-withdrawal-two-step-approve-mark-paid.md` covering: (a) split of Approve from MarkPaid, (b) five new `withdrawals` columns, (c) deprecation of `ApproveAndMarkWithdrawalPaidAction`, (d) constitution §XI deviation (Spatie media for bank proof) with proposed amendment-or-cleanup follow-up, (e) Related ADRs: ADR-0009, ADR-0028. Status must read `Accepted` before any code lands.

- [X] T002 Update `CLAUDE.md` "Architecture Decision Records (ADR)" list to include ADR-0032 with one-line summary, and append to the ADR enumeration block.

- [X] T003 Create migration `app/Modules/Settlement/Database/Migrations/2026_05_16_000001_alter_withdrawals_add_approve_pay_audit_columns.php` per `data-model.md §1`: adds nullable `approved_at TIMESTAMP`, `approved_by_admin_id BIGINT UNSIGNED FK→users restrictOnDelete`, `paid_by_admin_id BIGINT UNSIGNED FK→users restrictOnDelete`, `bank_transfer_reference VARCHAR(120)`, `admin_payment_note JSON`, plus UNIQUE index `withdrawals_vendor_transfer_ref_unique` on `(vendor_profile_id, bank_transfer_reference)`. Migration also runs the one-time backfill SQL for historical `paid` rows (`approved_at = processed_at`, `approved_by_admin_id = processed_by_user_id`, `paid_by_admin_id = processed_by_user_id`). Implement `down()` to drop all five columns and the unique index. Includes `declare(strict_types=1);` and anonymous-class shape per `.claude/rules/migrations.md`.

- [X] T004 [P] Update `app/Modules/Settlement/Database/Seeders/SettlementPermissionsSeeder.php` (create file if it does not yet exist, otherwise extend) to add three new permissions on the `admin` guard: `withdrawal.approve`, `withdrawal.mark_paid`, `withdrawal.view_audit`. Assign as per `data-model.md §8`: Settlement Admin / Finance Admin / Super Admin / Auditor.

- [X] T005 [P] Update `docs/specs/11_DB_Schema.md` §9 `withdrawals` table to reflect the five new columns + UNIQUE index. Mark the diff with `<!-- 2026-05-16 Phase 4.11 — finance audit columns -->`.

- [X] T006 [P] Update `docs/specs/09_Phasing_Plan.md` — insert "PHASE 4.11 — Withdrawal Proof & Finance Audit (1–2 days, Week 8)" entry between Phase 4.10 and Phase 5; cite ADR-0032 and link to this spec.

- [X] T007 [P] Update `docs/specs/01_PRD.md` §7.7 — add FR-EXT-205, FR-EXT-206, FR-EXT-207, FR-EXT-208, FR-EXT-209 verbatim from `spec.md` Traceability section. Remove the corresponding `⚠️ BACKFILL NEEDED` markers from `specs/033-withdrawal-proof-audit/spec.md`.

**Checkpoint**: Migration ready to run. Permissions exist. Docs aligned. ADR accepted.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Model + state machine + DTO + event. Every user story depends on these.

**⚠️ CRITICAL**: No user-story work begins until Phase 2 is green.

- [ ] T008 Run `php artisan migrate` locally; verify the migration applies cleanly and the backfill populates historical paid rows. Smoke-check via `php artisan tinker` that `Withdrawal::query()->where('status','paid')->whereNotNull('approved_at')->count()` matches the pre-migration paid-row count.

- [X] T009 Update `app/Modules/Settlement/Domain/Models/Withdrawal.php`: add the five new columns to `$fillable`; add `approved_at`, `paid_by_admin_id` to `$casts` (datetime + integer); add `'admin_payment_note'` to the `$translatable` array (alongside `rejected_reason`); add `'admin_payment_note' => 'array'` to `$casts`. Add two new `BelongsTo` relations: `approvedByAdmin()` and `paidByAdmin()` (mirror existing `processedBy()`). Verify the `bank_proof` media collection registration in `registerMediaCollections()` is on the `s3_private` disk (add `->useDisk('s3_private')` if missing).

- [X] T010 [P] Create `app/Modules/Settlement/Domain/States/WithdrawalStatus/Transitions/ApprovedToPaid.php`: a `Spatie\ModelStates\Transition` subclass guarded by `WithdrawalStatus::Approved`. The `handle()` body is intentionally minimal (just `status` change) — all field-setting and side effects live in `MarkWithdrawalPaidAction`.

- [X] T011 [P] Update or create `app/Modules/Settlement/Domain/States/WithdrawalStatus/WithdrawalState.php` to register the allowed transitions: `Pending→Approved` (`PendingToApproved`), `Pending→Rejected` (`PendingToRejected` — existing), `Approved→Paid` (`ApprovedToPaid`). Explicitly DO NOT register `Pending→Paid` or `Approved→Rejected` or `Approved→Pending`. If a `PendingToPaid` class exists, delete it.

- [X] T012 [P] Create `app/Modules/Settlement/Domain/States/WithdrawalStatus/Transitions/PendingToApproved.php` if it does not exist (or update existing). Body is minimal — fields are set in `ApproveWithdrawalAction`.

- [X] T013 [P] Create `app/Modules/Settlement/Domain/Events/WithdrawalApproved.php`: `final readonly class` with constructor `(int $withdrawalId, string $withdrawalPublicId, int $vendorProfileId, int $approvedByAdminId)`.

- [X] T014 [P] Create `app/Modules/Settlement/Domain/Exceptions/InvalidWithdrawalTransitionException.php`: extends `DomainException`. Constructor takes `(WithdrawalStatus $current, WithdrawalStatus $attempted)` and produces a translatable error message via `__('settlement.errors.withdrawal_state', [...])`.

- [X] T015 [P] Create `app/Modules/Settlement/Domain/Exceptions/DuplicateBankTransferReferenceException.php`: extends `DomainException`. Constructor takes `(int $vendorProfileId, string $reference)`.

- [X] T016 [P] Create `app/Modules/Settlement/Application/DTOs/MarkWithdrawalPaidInput.php`: `final readonly class` per `data-model.md §4`. Fields: `public string $bankTransferReference`, `public UploadedFile $proofFile`, `/** @var array{en?: string, ar?: string} */ public array $paymentNote = []`. Add `assertValid()` instance method that throws `InvalidArgumentException` on empty reference, missing file, oversize file, wrong MIME, or empty `paymentNote` if the array key is present.

**Checkpoint**: Schema migrated, model + state machine + DTO + event ready. User-story work can start in parallel.

---

## Phase 3: User Story 1 — Two-step admin payout with proof and reference (Priority: P1) 🎯 MVP

**Goal**: Admin can `Approve` a pending withdrawal, then later `Mark Paid` with required proof + bank-transfer reference + optional EN/AR note. State machine refuses any skip; ledger group posts exactly once.

**Independent Test**: Run quickstart.md §3 steps 1–4 manually OR run `tests/Feature/Modules/Settlement/WithdrawalApproveMarkPaidTest.php` cases 1, 2, 3, 4, 5, 6, 9 — all green.

### Tests for US1 (written FIRST, must FAIL initially)

- [X] T017 [P] [US1] Create `tests/Feature/Modules/Settlement/WithdrawalApproveMarkPaidTest.php` skeleton with Pest `describe('US1 — admin payout')` block and these `it()` cases per `data-model.md §7`: case 1 (request → reserve ledger), case 2 (approve), case 3 (mark paid), case 4 (cannot mark paid without reference), case 5 (cannot mark paid without proof), case 6 (cannot mark paid on pending), case 9 (idempotent replay). All tests start as failing assertions.

- [X] T018 [P] [US1] Add test factory updates if needed at `app/Modules/Settlement/Database/Factories/WithdrawalFactory.php` for the four states (pending / approved / paid / rejected) with realistic bank-account snapshots and ledger-entry links for already-`paid` fixtures.

### Implementation for US1

- [X] T019 [US1] Create `app/Modules/Settlement/Application/Actions/ApproveWithdrawalAction.php` per `data-model.md §3.1`. Constructor empty. `execute(Withdrawal $w, User $admin): Withdrawal` wraps in `DB::transaction`; idempotency check via `idempotency_keys` table on key `wd_approve:{$w->id}`; calls `$w->status->transitionTo(ApprovedState::class)`; sets `approved_at` + `approved_by_admin_id`; inserts `audit_logs` row; `DB::afterCommit(fn () => event(new WithdrawalApproved(...)))`. Throws `InvalidWithdrawalTransitionException` if pre-state is not `Pending`.

- [X] T020 [US1] Create `app/Modules/Settlement/Application/Actions/MarkWithdrawalPaidAction.php` per `data-model.md §3.2`. Constructor injects `EloquentWalletRepository`, `LedgerWriter`, `WalletLocker` (same trio as the existing combined action). `execute(Withdrawal $w, MarkWithdrawalPaidInput $input, User $admin): Withdrawal` calls `$input->assertValid()` first; acquires wallet lock; `DB::transaction` body checks state, enforces UNIQUE on `(vendor_profile_id, bank_transfer_reference)` preemptively, attaches media via `$w->addMedia($input->proofFile)->toMediaCollection('bank_proof')`, posts settle ledger group via existing `PostLedgerTransactionAction` invocation pattern (copy-paste from `ApproveAndMarkWithdrawalPaidAction.php` lines 65–123, idempotency key `wd_settle:{$w->id}`), updates row, inserts audit row, dispatches existing `WithdrawalPaid` event via `DB::afterCommit`. Releases lock in `finally`.

- [X] T021 [US1] Convert `app/Modules/Settlement/Application/Actions/ApproveAndMarkWithdrawalPaidAction.php` into a `@deprecated` wrapper per `data-model.md §3.3`: delegates to the two new Actions, supplies a `LEGACY-{public_id}` reference and the bilingual legacy note. Add PHPDoc `@deprecated since 4.11 use ApproveWithdrawalAction + MarkWithdrawalPaidAction`.

- [X] T022 [US1] Rewrite `app/Modules/Settlement/Filament/Resources/WithdrawalsQueueResource.php` row-actions block: remove the combined `approve` action; add two new row actions per `contracts/admin-approve-action.md` and `contracts/admin-mark-paid-action.md`. Both call `auth()->user()->can(...)` in `visible()` and delegate to the new Actions via `app(ApproveWithdrawalAction::class)->execute(...)` / `app(MarkWithdrawalPaidAction::class)->execute(...)`. Use `Notification::make()` for in-app feedback per `.claude/rules/filament-components.md §4`. Keep `Reject` action untouched.

- [X] T023 [P] [US1] Update `app/Modules/Settlement/Resources/lang/en/settlement.php` with the keys listed in `contracts/admin-approve-action.md` and `contracts/admin-mark-paid-action.md` (notifications.*, fields.*, validation.*, errors.*).

- [X] T024 [P] [US1] Update `app/Modules/Settlement/Resources/lang/ar/settlement.php` with the same keys in Arabic per the same contracts.

- [ ] T025 [US1] Run `php artisan shield:generate --all` and verify `withdrawal.approve`, `withdrawal.mark_paid`, `withdrawal.view_audit` permissions appear in the Filament Shield permission matrix.

- [X] T026 [US1] Fill in the 7 US1 test bodies in `tests/Feature/Modules/Settlement/WithdrawalApproveMarkPaidTest.php` against the new Actions. Use `Spatie\MediaLibrary\Conversions\FileAdder` test helpers + an `UploadedFile::fake()` PDF. Test 8 (append-only invariant) is moved to Polish — it spans all stories. Run the suite — all 7 US1 cases must pass.

- [ ] T027 [US1] Run `./vendor/bin/pint app/Modules/Settlement/` and `./vendor/bin/phpstan analyse app/Modules/Settlement/` — green before moving on.

**Checkpoint**: US1 is independently demonstrable — admin can complete the two-step flow end-to-end via Filament; no vendor-facing changes yet required.

---

## Phase 4: User Story 2 — Vendor sees timeline, reference, and proof in their wallet (Priority: P1)

**Goal**: A vendor opening their wallet → withdrawals can see, per withdrawal, the full lifecycle timeline, the bank-transfer reference (when paid), the admin payment note resolved to their locale, and a signed-URL download of the proof. A different vendor cannot access it.

**Independent Test**: After US1 has produced a paid withdrawal, hit `GET /api/v1/vendor/withdrawals/{public_id}` as the owning vendor and assert the response shape matches `contracts/vendor-show-withdrawal.md`. Then hit it as a different vendor and assert 404. Then check the Filament vendor wallet page renders the proof-download button.

### Tests for US2

- [X] T028 [P] [US2] Append two new Pest `describe('US2 — vendor view')` cases to `tests/Feature/Modules/Settlement/WithdrawalApproveMarkPaidTest.php`: case 7 (different vendor → 404), bilingual dataset asserting `admin_payment_note` resolves to EN when `Accept-Language: en` and to AR when `Accept-Language: ar` on the same paid withdrawal. Assert `proof_download_url` is a non-empty signed URL with a 15-minute expiry parameter.

### Implementation for US2

- [X] T029 [US2] Update `app/Modules/Settlement/Http/Resources/WithdrawalResource.php`: add `approved_at`, `paid_at`, `bank_transfer_reference`, `admin_payment_note` (resolved via `$this->getTranslation('admin_payment_note', app()->getLocale())`), `proof_download_url` (call `$this->getFirstMedia('bank_proof')?->getTemporaryUrl(now()->addMinutes(15))`), `proof_download_url_expires_at`, `timeline` (compose array per `contracts/vendor-show-withdrawal.md`). Add Scribe `@response` PHPDoc blocks for the four scenarios (paid EN, paid AR, pending, rejected) — file references under `docs/api/responses/vendor/`.

- [X] T030 [US2] Verify the existing vendor controller `app/Modules/Settlement/Http/Controllers/Vendor/WithdrawalController.php` `show()` returns the Resource and uses the existing `findByPublicIdForVendor()` repository method that already returns 404 (not 403) on tenant mismatch. If not, add `abort_unless($withdrawal->vendor_profile_id === auth()->user()->vendorProfile->id, 404)`.

- [X] T031 [P] [US2] Update `app/Modules/Settlement/Http/Resources/WithdrawalListResource.php` (or the relevant list resource — create if missing per `contracts/vendor-list-withdrawals.md`): add `approved_at`, `paid_at`, `bank_transfer_reference`, `has_proof` (boolean from `$this->getFirstMedia('bank_proof') !== null`). Do NOT include `proof_download_url` on the list. Add Scribe annotations.

- [X] T032 [P] [US2] Create `docs/api/responses/vendor/` directory and four example JSON files: `show_withdrawal_paid_en.json`, `show_withdrawal_paid_ar.json`, `show_withdrawal_pending.json`, `show_withdrawal_rejected.json`. Content matches `contracts/vendor-show-withdrawal.md`.

- [X] T033 [US2] Extend `app/Modules/Settlement/Filament/Vendor/Pages/VendorWalletPage.php` withdrawals table: add a "Status timeline" stacked column per `.claude/rules/filament-components.md §2 Layout columns`, a "Reference" text column (visible only when status=`paid`), and a "Proof" action column rendering a `Tables\Actions\Action` button that calls `$record->getFirstMedia('bank_proof')?->getTemporaryUrl(now()->addMinutes(15))` and opens in a new tab (only when status=`paid` AND the media exists).

- [ ] T034 [US2] Run the US2 tests + the US1 tests together — both green.

- [ ] T035 [US2] Manual smoke test per quickstart.md §3 step 5 (vendor view) and step 6 (different vendor 404). Capture pass evidence in the PR description later.

**Checkpoint**: US1 + US2 both work independently and together. The vendor self-serve story is closed.

---

## Phase 5: User Story 3 — Admin detail page surfaces full approval + payment audit (Priority: P2)

**Goal**: Opening any withdrawal in the admin panel shows a single page with the full audit trail: who/when at each step, reference, payment note (EN + AR side-by-side), inline proof preview, links to related ledger transaction groups. Permission-gated.

**Independent Test**: As an admin with `withdrawal.view_audit`, open `/admin/withdrawals/{public_id}` for a paid withdrawal and visually confirm all audit elements render. As an admin WITHOUT `withdrawal.view_audit`, confirm the reference + proof areas are hidden. As an admin opening a `pending` withdrawal, confirm sections show empty-state messaging not blank fields.

### Tests for US3

- [X] T036 [P] [US3] Append two new Pest `describe('US3 — admin audit page')` cases to `tests/Feature/Modules/Settlement/WithdrawalApproveMarkPaidTest.php`: (a) Filament page renders with all audit fields for a paid withdrawal (use Livewire test helpers per the project's existing Filament test patterns — see any `*FilamentTest.php` already in `tests/Feature/Modules/Settlement/`); (b) an admin without `withdrawal.view_audit` permission cannot see the reference or proof preview.

### Implementation for US3

- [X] T037 [US3] Add a `ViewWithdrawal` page at `app/Modules/Settlement/Filament/Resources/WithdrawalResource/Pages/ViewWithdrawal.php` if not already present, and wire it into `WithdrawalResource::getPages()` with `'view' => ViewWithdrawal::route('/{record}')`.

- [X] T038 [US3] Build the audit infolist on `app/Modules/Settlement/Filament/Resources/WithdrawalResource.php`'s `infolist()` method: use `Filament\Infolists\Components\Section` blocks per `.claude/rules/filament-components.md §3` for (a) Request (vendor + bank snapshot + amount + requested_at + requested_by), (b) Approval (approved_at + approved_by_admin_id — hidden if null with placeholder "Not yet approved"), (c) Payment (paid_at + paid_by_admin_id + bank_transfer_reference + admin_payment_note EN/AR via two-column `Grid`), (d) Proof (Spatie media `ImageEntry` for image MIME or a download link for PDF), (e) Ledger (TextEntry rows linking to `LedgerTransactionGroupResource::getUrl('view', ['record' => $reservedGroupId])` for each of reserved / settled / rejected, with empty-state when null). Gate `bank_transfer_reference`, `admin_payment_note`, and the Proof section with `->visible(fn () => auth()->user()->can('withdrawal.view_audit'))`.

- [X] T039 [US3] Add a "View ledger groups" sidebar action on `ViewWithdrawal` linking directly to `WalletLedgerViewerResource::getUrl('view', ['record' => $withdrawal->reservedLedgerEntry?->wallet_id])` so the auditor can pivot to the wallet view in one click.

- [ ] T040 [US3] Run all three US test groups — green.

- [ ] T041 [US3] Run `php artisan filament:cache-components` and manually open the admin detail page for one paid + one pending + one rejected withdrawal — confirm rendering matches expectation.

**Checkpoint**: All three user stories independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Append-only invariant test, registry + collection updates, deprecation cleanup signal, final quality gates.

- [X] T042 [P] Add Pest test case 8 (append-only invariant for `wallet_ledger`) to `tests/Feature/Modules/Settlement/WithdrawalApproveMarkPaidTest.php` per `data-model.md §7`. Snapshot `wallet_ledger` row IDs and values after each Action; assert no UPDATE / DELETE happened to any historical row across the full US1 happy-path.

- [ ] T043 [P] Verify `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` still passes — withdrawals is in the "status-only mutable" exception list, so adding columns must not trip the test. If the architecture test enumerates columns explicitly, update its allow-list to permit `approved_at`, `approved_by_admin_id`, `paid_by_admin_id`, `bank_transfer_reference`, `admin_payment_note` as "set-once" columns.

- [X] T044 [P] Update `.specify/memory/api-registry.md`: bump the "Resource" column for the two existing rows `GET /api/v1/vendor/withdrawals` and `GET /api/v1/vendor/withdrawals/{public_id}` to reflect extended response shapes per `contracts/vendor-list-withdrawals.md` and `contracts/vendor-show-withdrawal.md`. Add a dated note `2026-05-16 — Phase 4.11 audit fields`.

- [X] T045 [P] Update Bruno collection: `docs/api/collections/settlement/05_show_withdrawal.bru` — paste in the paid-EN response example from `contracts/vendor-show-withdrawal.md`. `03_list_withdrawals.bru` — paste in the list response example from `contracts/vendor-list-withdrawals.md`.

- [X] T046 [P] Add a TODO/issue link or `@todo Phase 8` comment block at the top of `app/Modules/Settlement/Application/Actions/ApproveAndMarkWithdrawalPaidAction.php` calling out: "Remove this wrapper once no internal consumer remains. Search the repo for `ApproveAndMarkWithdrawalPaidAction::class` before deleting."

- [ ] T047 Run `./vendor/bin/pest --bail` — full suite green.

- [ ] T048 Run `php artisan ledger:diff` per quickstart.md §5 — must return exit code 0 ("0 wallets with drift").

- [ ] T049 Run `./vendor/bin/pint` and `./vendor/bin/phpstan analyse` over the whole repo — both green.

- [ ] T050 Final pass against `specs/033-withdrawal-proof-audit/checklists/requirements.md` — re-verify every checklist item still passes after implementation; tick any that were deferred.

---

## Dependencies & Execution Order

### Phase dependencies

- **Phase 1 (Setup)**: no dependencies — ADR + migration + permission seeder + spec backfills can start in parallel
- **Phase 2 (Foundational)**: depends on Phase 1 — model + state machine + DTO + event are gated by the migration applying
- **Phase 3 (US1)**: depends on Phase 2 — Actions + Filament queue resource
- **Phase 4 (US2)**: depends on Phase 2, can start in parallel with Phase 3 once foundations land — vendor Resource + vendor wallet page
- **Phase 5 (US3)**: depends on Phase 2, can start in parallel with Phase 3 and Phase 4 — admin Infolist + view page
- **Phase 6 (Polish)**: depends on Phases 3, 4, 5 — invariant test, registry/collection docs, final gates

### Within each phase

- Tests are written first within each story phase per constitution §VII (test-first for money flows)
- Models → DTOs → Actions → HTTP/Filament — strict layer order per constitution `/speckit.tasks` guidance
- One Pest file holds all tests for this feature (`WithdrawalApproveMarkPaidTest.php`) — story-specific `describe()` blocks keep them isolated

### Parallel opportunities

- T004, T005, T006, T007 (Setup docs/permissions, no overlap) — parallel
- T010–T016 (Foundational state machine + DTO + event + exceptions, distinct files) — parallel
- T017 + T018 (US1 test skeleton + factory updates) — parallel
- T023 + T024 (EN + AR translations, distinct files) — parallel
- T028 (US2 tests) and T031, T032 (list resource + example JSONs) — parallel
- T036 (US3 tests) parallel with US3 implementation (T037–T039 are mostly the same file `WithdrawalResource.php`, so NOT parallel within themselves)
- T042–T046 (Polish: all distinct files) — parallel

---

## Parallel Example: Foundational phase

```bash
# Once T008 (migration applied) is done, launch in parallel:
Task: "Create ApprovedToPaid transition (T010)"
Task: "Update WithdrawalState transition map (T011)"
Task: "Create PendingToApproved transition (T012)"
Task: "Create WithdrawalApproved event (T013)"
Task: "Create InvalidWithdrawalTransitionException (T014)"
Task: "Create DuplicateBankTransferReferenceException (T015)"
Task: "Create MarkWithdrawalPaidInput DTO (T016)"
```

## Parallel Example: User Story 1 (after T019, T020, T021, T022 are done sequentially because they share workflow)

```bash
# Translations are independent files:
Task: "EN translations (T023)"
Task: "AR translations (T024)"
```

---

## Implementation Strategy

### MVP First (US1 only)

1. Phase 1: Setup — author ADR-0032, run migration, seed permissions, backfill docs.
2. Phase 2: Foundational — model + state machine + DTO + event.
3. Phase 3: User Story 1 — admin two-step flow working end-to-end via Filament.
4. **STOP and VALIDATE** — quickstart.md §3 steps 1–4 manually; full US1 Pest suite green.
5. Deploy to staging if desired — vendor-facing field additions are backward-compatible (vendor sees `null` for the new fields until US2 ships, but no break).

### Incremental delivery

1. MVP after Phase 3 (US1) — admin operations rationalized; audit columns populated.
2. Add Phase 4 (US2) — vendors gain self-serve view + signed proof URLs.
3. Add Phase 5 (US3) — admin detail page consolidates the audit story.
4. Polish — invariant test, docs, registry, deprecation signal.

### Solo dev order (Ibrahim, single-developer)

Linear: T001 → T002 → T003 → T004 → T005-T007 (batch in 1 commit) → T008 → T009 → T010-T016 (1 commit) → T017-T018 → T019-T026 → T027 (gate) → T028 → T029-T035 → T036 → T037-T041 → T042-T050. Estimated 1.5–2 days.

---

## Notes

- Total tasks: **50**
- US1: T017–T027 (11 tasks)
- US2: T028–T035 (8 tasks)
- US3: T036–T041 (6 tasks)
- Setup + Foundational + Polish: T001–T016 + T042–T050 (25 tasks)
- Parallelizable [P] tasks: 17 of 50
- Test tasks: T017, T018, T026, T028, T036, T042 — all in the same Pest file (one file, multiple `describe()` blocks)
- Constitution §VII test-first satisfied: every story phase opens with its test task before any implementation file is touched in that phase
- Idempotency assertions explicit (T026 case 9) per constitution §VIII
- Bilingual coverage explicit (T028 dataset, T023+T024 translations) per constitution §IV
- Append-only invariant explicit (T042) per constitution §V
- Domain events fire `DB::afterCommit` (T019, T020 wording) per constitution §IX
- Audit log row per state change (T019, T020 wording) per CLAUDE.md §10
- No package additions — every dependency is already in `docs/specs/10_Package_List.md`
- Avoid: starting US1 implementation before T017 fails; modifying any append-only table; running migration in production without staging dry-run; force-push at any point
