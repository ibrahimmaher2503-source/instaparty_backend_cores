# Tasks: Vendor Document Compliance Lifecycle

**Feature**: `022-vendor-doc-compliance`  
**Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)  
**Phase**: 6.9 — Vendor Document Compliance ⚠️ PHASE BACKFILL NEEDED  
**Module**: `app/Modules/Identity/`  
**Tests**: Included — explicitly required by spec exit criteria

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no conflicting writes)
- **[Story]**: Maps to spec.md user story number

---

## Phase 1: Setup (Blocking Gate)

**Purpose**: ADR must be accepted before any migration is written (Constitution §VI).

- [x] T001 Write and finalize `docs/adr/ADR-0021-vendor-doc-expiry.md` — capture the 6 key decisions from plan.md §ADR-0021, set status to `Accepted` before proceeding to T002

---

## Phase 2: Foundational (Migrations + Models + Enums)

**Purpose**: Schema and domain layer that ALL user stories depend on. Must be fully complete before any story work begins.

⚠️ CRITICAL: No user story work can begin until this phase is complete.

- [ ] T002 Create migration `app/Modules/Identity/Database/Migrations/2026_05_04_000001_alter_vendor_profiles_add_suspension_reason.php` — add `suspension_reason JSON NULL` column to `vendor_profiles`; charset utf8mb4; no index change needed
- [ ] T003 Create migration `app/Modules/Identity/Database/Migrations/2026_05_04_000002_alter_vendor_documents_add_expiry_columns.php` — add `expires_at DATE NULL`, `is_critical TINYINT(1) UNSIGNED NOT NULL DEFAULT 0`, `last_reminder_sent_at DATE NULL`; add composite index `(expires_at, is_critical)` and index on `last_reminder_sent_at`
- [ ] T004 Create migration `app/Modules/Identity/Database/Migrations/2026_05_04_000003_create_vendor_compliance_events_table.php` — append-only table; columns: `bigIncrements('id')`, `char('public_id',26)->unique()`, `foreignId('vendor_profile_id')->constrained()->restrictOnDelete()`, `foreignId('document_id')->nullable()->constrained('vendor_documents')->nullOnDelete()`, `enum('event_type',['reminder_sent','expired','auto_suspended','manually_overridden'])`, `timestamp('occurred_at')->useCurrent()`, `foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete()`, `json('reason')->nullable()`; NO `updated_at`; NO `softDeletes()`; indexes: `(vendor_profile_id, event_type, occurred_at)`, `(document_id, event_type)`, `(event_type, occurred_at)`; charset utf8mb4
- [x] T005 Run `php artisan migrate` and confirm all 3 migrations apply cleanly
- [x] T006 [P] Create `app/Modules/Identity/Domain/Enums/ComplianceEventType.php` — backed string enum with cases `ReminderSent`, `Expired`, `AutoSuspended`, `ManuallyOverridden`; add `label(): array` returning EN+AR labels for each case
- [x] T007 [P] Create `app/Modules/Identity/Domain/Models/VendorComplianceEvent.php` — `$fillable`, `$casts` (`occurred_at` → `datetime`), `$translatable = ['reason']` (spatie/laravel-translatable), relationships `vendorProfile()` and `document()`, NO `$timestamps` (only `occurred_at` via `CREATED_AT = 'occurred_at'; const UPDATED_AT = null`)
- [x] T008 [P] Update `app/Modules/Identity/Domain/Models/VendorDocument.php` — add casts for `expires_at` (→ `date`), `is_critical` (→ `bool`), `last_reminder_sent_at` (→ `date`); add scopes: `scopeWithExpiry($q)` (whereNotNull expires_at), `scopeExpiringWithinDays($q, int $days)`, `scopeExpired($q)`
- [x] T009 [P] Update `app/Modules/Identity/Domain/Models/VendorProfile.php` — add `suspension_reason` to `$translatable` array and `$fillable`

**Checkpoint**: Schema and domain layer ready. `php artisan migrate` green. All 4 model/enum files exist.

---

## Phase 3: User Story 5 — Admin Sets Document Expiry During Approval (Priority: P1) 🎯 DATA ENTRY GATE

**Story**: US5 — Admin sets `expires_at` + `is_critical` on a vendor document during the approval flow.  
**Why first**: US1 (reminders) and US2 (auto-suspend) require documents to have `expires_at` populated; this story is the entry point for all expiry data.  
**Independent Test**: Create a vendor document in Filament, set `expires_at = tomorrow`, `is_critical = true`, save — verify both values are persisted.

- [x] T010 [US5] Create `app/Modules/Identity/Application/Actions/SetDocumentExpiryAction.php` — `execute(VendorDocument $document, Carbon $expiresAt, bool $isCritical): VendorDocument`; validate `$expiresAt->isFuture()` (throw `\InvalidArgumentException` with translatable message if past); wrap `$document->update(['expires_at' => $expiresAt->toDateString(), 'is_critical' => $isCritical])` in `DB::transaction`; return updated document; no domain event (configuration action)
- [x] T011 [US5] Update the existing Filament `VendorDocumentResource` form (find in `app/Modules/Identity/Filament/Resources/VendorDocumentResource.php` or equivalent) — add `DatePicker::make('expires_at')->minDate(today()->addDay())->nullable()->label(__('identity.expires_at'))` and `Toggle::make('is_critical')->label(__('identity.is_critical'))` in the document edit/approval section; call `SetDocumentExpiryAction` from the form save or via a dedicated Filament Action
- [x] T012 [US5] Write Pest tests in `tests/Feature/Modules/Identity/VendorDocumentComplianceTest.php` — two cases: (a) `SetDocumentExpiryAction::execute()` with a future date persists correctly; (b) `SetDocumentExpiryAction::execute()` with a past date throws `InvalidArgumentException` — group `compliance`

**Checkpoint**: Admin can set expiry on any document. Pest T012 green.

---

## Phase 4: User Story 1 — Vendor Receives Expiry Reminders (Priority: P1)

**Story**: US1 — Daily command fires reminder notifications at 30 / 14 / 7 / 1 day(s) before expiry, idempotent.  
**Independent Test**: Seed a document with `expires_at = today + 30 days`, run `php artisan identity:check-document-expiry`, assert `vendor.doc_expiring_30d` notification was dispatched once.

- [x] T013 [US1] Create `app/Modules/Communication/Database/Seeders/DocExpiryNotificationTemplateSeeder.php` — seed 10 rows into `notification_templates` (5 event_keys × 2 channels `in_app`+`email`): `vendor.doc_expiring_30d`, `vendor.doc_expiring_14d`, `vendor.doc_expiring_7d`, `vendor.doc_expiring_1d`, `vendor.doc_expired`; each row must have `audience='vendor'`, `subject = {"en":"...","ar":"..."}`, `body = {"en":"...","ar":"..."}` with `{doc_type}` and `{vendor_name}` placeholders; run seeder in `DatabaseSeeder` or via `php artisan db:seed --class=DocExpiryNotificationTemplateSeeder`
- [x] T014 [US1] Create `app/Modules/Identity/Console/Commands/CheckDocumentExpiryCommand.php` — artisan signature `identity:check-document-expiry`; implement REMINDER logic only in this task: query `VendorDocument::withExpiry()->expiringWithinDays(30)->get()`; for each doc check if `days_until_expiry` is in `[30,14,7,1]` AND `last_reminder_sent_at != today()`; dispatch appropriate notification template via `DispatchNotificationAction` (Communication module contract); update `last_reminder_sent_at = today()`; write `vendor_compliance_events` row with `event_type=reminder_sent`; wrap each document in its own `try/catch` so one failure doesn't block others
- [x] T015 [US1] Register `CheckDocumentExpiryCommand` in the Laravel scheduler — add `Schedule::command('identity:check-document-expiry')->dailyAt('02:00')` in `app/Console/Kernel.php` or in `IdentityServiceProvider::boot()` via `$this->callAfterResolving(Schedule::class, ...)`; also register command in `IdentityServiceProvider` commands array
- [x] T016 [US1] Write Pest tests for reminder cascade in `tests/Feature/Modules/Identity/VendorDocumentComplianceTest.php` — 5 cases using `$this->travelTo()`: (a) 30-day reminder fires + `last_reminder_sent_at` updated; (b) 14-day reminder fires; (c) 7-day reminder fires; (d) 1-day reminder fires; (e) no duplicate when run twice same day — group `compliance`

**Checkpoint**: Daily command fires reminders. T016 (5 time-travel tests) green.

---

## Phase 5: User Story 2 — Critical Doc Expiry Triggers Auto-Suspension (Priority: P1)

**Story**: US2 — When an `is_critical=true` document's `expires_at` has passed, the vendor is auto-suspended; existing bookings continue; per-type approvals are revoked.  
**Independent Test**: Seed a vendor with a critical doc `expires_at = yesterday`, run the command, assert `vendor_profiles.approval_status = 'suspended'` and a `auto_suspended` compliance event exists.

- [ ] T017 [P] [US2] Create `app/Modules/Identity/Domain/Events/VendorAutoSuspended.php` — readonly constructor `(VendorProfile $vendorProfile, VendorDocument $document)`; no logic, pure data carrier
- [ ] T018 [US2] Create `app/Modules/Identity/Application/Actions/AutoSuspendForExpiredDocAction.php` — `execute(VendorDocument $document): VendorProfile`; inside `DB::transaction`: (1) resolve vendor profile, (2) update `vendor_profiles`: `approval_status='suspended'`, `suspended_at=now()`, `suspended_by=null` (system), `suspension_reason={"en":"Automatically suspended: {doc_type} expired on {date}","ar":"تم التعليق تلقائياً: وثيقة {doc_type} انتهت صلاحيتها في {date}"}`, (3) insert `vendor_compliance_events` row with `event_type=auto_suspended`, `admin_id=null`, `reason=<same JSON>`, (4) `DB::afterCommit(fn () => event(new VendorAutoSuspended($vendorProfile, $document)))` — return updated profile; do NOT call `SuspendVendorAction` (that uses auth()->id())
- [ ] T019 [US2] Extend `CheckDocumentExpiryCommand` — add expired-doc processing after the reminder block: query `VendorDocument::withExpiry()->expired()->get()`; for each doc: if `is_critical=true` and vendor not already suspended → call `AutoSuspendForExpiredDocAction::execute($doc)`; elif `is_critical=false` and `last_reminder_sent_at != today()` → dispatch `vendor.doc_expired` notification + update `last_reminder_sent_at`
- [ ] T020 [US2] Create `app/Modules/Identity/Application/Listeners/SendDocExpiredNotificationListener.php` — handles `VendorAutoSuspended`; dispatches `vendor.doc_expired` notification to the vendor via `DispatchNotificationAction`; queued listener
- [ ] T021 [US2] Extend `app/Modules/Identity/Application/Listeners/RevokeAllVendorTypesOnStatusChange.php` — add `VendorAutoSuspended` to the union type on `handle(VendorSuspended|VendorRejected|VendorAutoSuspended $event)`; extract `$vendorProfile` from the new event type
- [ ] T022 [US2] Register new listener and updated listener in `app/Modules/Identity/Providers/IdentityServiceProvider.php` — add `Event::listen(VendorAutoSuspended::class, RevokeAllVendorTypesOnStatusChange::class)` and `Event::listen(VendorAutoSuspended::class, SendDocExpiredNotificationListener::class)`
- [ ] T023 [US2] Write Pest tests in `tests/Feature/Modules/Identity/VendorDocumentComplianceTest.php` — 5 cases: (a) critical doc expired → `approval_status=suspended` + compliance event `auto_suspended`; (b) `VendorAutoSuspended` event dispatched; (c) per-type approvals revoked after auto-suspend; (d) existing bookings `lifecycle_status` unchanged after suspend; (e) non-critical expired doc → notification only, no suspend — group `compliance`

**Checkpoint**: Auto-suspension fires for critical expired docs. T023 (5 cases) green.

---

## Phase 6: User Story 4 — Admin Grants Grace Period Override (Priority: P2)

**Story**: US4 — Admin un-suspends a vendor for 14 days via a Filament action with a bilingual reason; override is fully auditable.  
**Independent Test**: Factory-create an auto-suspended vendor, call `GrantDocGracePeriodAction::execute()`, assert `approval_status='approved'` and a `manually_overridden` compliance event with `admin_id` set.

- [ ] T024 [P] [US4] Create `app/Modules/Identity/Domain/Events/VendorGracePeriodGranted.php` — readonly constructor `(VendorProfile $vendorProfile, VendorDocument $document, int $grantedByAdminId)`
- [ ] T025 [US4] Create `app/Modules/Identity/Application/Actions/GrantDocGracePeriodAction.php` — `execute(VendorDocument $document, User $admin, array $reason): VendorProfile` where `$reason = ['en' => '...', 'ar' => '...']`; validate both locales non-empty (throw `\InvalidArgumentException` otherwise); inside `DB::transaction`: (1) extend `vendor_documents.expires_at` = `today()->addDays(14)->toDateString()`, (2) update vendor profile: `approval_status='approved'`, `suspended_at=null`, `suspended_by=null`, `suspension_reason=null`, (3) insert `vendor_compliance_events`: `event_type=manually_overridden`, `admin_id=$admin->id`, `reason=$reason`, (4) `DB::afterCommit(fn () => event(new VendorGracePeriodGranted(...)))` — return updated profile
- [ ] T026 [US4] Create `app/Modules/Identity/Application/Listeners/SendGracePeriodGrantedNotificationListener.php` — handles `VendorGracePeriodGranted`; dispatches a grace period confirmation notification (use `vendor.doc_expiring_7d` channel or add a dedicated `vendor.doc_grace_granted` template in the seeder); queued listener
- [ ] T027 [US4] Register listeners in `IdentityServiceProvider` — `Event::listen(VendorGracePeriodGranted::class, SendGracePeriodGrantedNotificationListener::class)`
- [ ] T028 [US4] Write Pest tests in `tests/Feature/Modules/Identity/VendorDocumentComplianceTest.php` — 4 cases: (a) grace period un-suspends vendor + extends `expires_at` by 14 days + compliance event logged with `admin_id`; (b) bilingual reason required (empty EN or AR fails); (c) after grace period lapses, daily command re-suspends + new `auto_suspended` event; (d) `vendor_compliance_events` record has both EN + AR reason non-empty — group `compliance`

**Checkpoint**: Grace period override works. T028 (4 cases) green. Total Pest: 16 cases green.

---

## Phase 7: User Story 3 — Admin Compliance Dashboard (Priority: P2)

**Story**: US3 — Two dashboard widgets (expiring this month, auto-suspended vendors) + the `ExpiredDocsResource` queue page with all admin actions wired up.  
**Independent Test**: Seed 3 docs expiring this month + 2 auto-suspended vendors; open `/admin`; verify widget counts and ExpiredDocsResource rows.

- [ ] T029 [US3] Create `app/Modules/Identity/Filament/Widgets/VendorComplianceWidget.php` — extends `\Filament\Widgets\StatsOverviewWidget`; 3 stats: (1) "Expiring This Month" (`VendorDocument::withExpiry()->whereBetween('expires_at', [today(), today()->endOfMonth()])->count()`), (2) "Auto-Suspended Vendors" (`VendorProfile::where('approval_status','suspended')->whereHas('complianceEvents', fn($q) => $q->where('event_type','auto_suspended'))->count()`, color `danger`), (3) "Active Grace Periods" (count where `event_type=manually_overridden` and corresponding doc `expires_at > today()`, color `warning`)
- [ ] T030 [US3] Create `app/Modules/Identity/Filament/Resources/ExpiredDocsResource.php` — model `VendorDocument`; `navigationGroup = 'Vendor Compliance'`; default scope `whereNotNull('expires_at')->where('expires_at','<=',today()->addDays(7))`; table columns: vendor name (TextColumn linkable to vendor profile), doc type badge, `expires_at` with color coding (danger = expired, warning = ≤7 days, info = ≤14 days), `is_critical` IconColumn boolean, vendor `approval_status` badge; per-row actions: (a) `Renew` Action — form with `DatePicker('expires_at')->minDate(today()->addDay())` + `Toggle('is_critical')` → calls `SetDocumentExpiryAction`; (b) `Grant Grace Period` Action — form with `Textarea('reason_en')->required()` + `Textarea('reason_ar')->required()` → calls `GrantDocGracePeriodAction`; `->visible(fn($record) => $record->vendor->approval_status === 'suspended')`; (c) `Suspend Manually` Action — `->requiresConfirmation()` → calls `AutoSuspendForExpiredDocAction`; `->visible(fn($record) => $record->vendor->approval_status !== 'suspended')`; all actions send Filament `Notification::make()->success()` on completion
- [ ] T031 [US3] Register `VendorComplianceWidget` in `app/Modules/Identity/Providers/IdentityServiceProvider.php` — add to the panel's `widgets()` array or register via `Filament::registerWidgets([VendorComplianceWidget::class])`
- [ ] T032 [US3] Run `php artisan shield:generate --all` to generate permissions for `ExpiredDocsResource`; verify new permissions appear in `config/filament-shield.php` or the Shield permission list

**Checkpoint**: Dashboard widgets accurate. ExpiredDocsResource shows correct docs with all 3 actions wired.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T033 [P] Backfill schema docs — add `vendor_compliance_events` table spec + new `vendor_profiles.suspension_reason` column + 3 new `vendor_documents` columns to `docs/specs/11_DB_Schema.md` Identity section
- [ ] T034 [P] Backfill `docs/specs/09_Phasing_Plan.md` — add Phase 6.9 entry: name, tables touched, exit criteria, cut-list
- [ ] T035 Run full quality gate: `./vendor/bin/pint` → `./vendor/bin/phpstan analyse` → `./vendor/bin/pest --group=compliance --bail` — all must be green before considering the feature complete

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1 (ADR) 
    └── Phase 2 (Migrations + Models) — BLOCKS all user stories
            ├── Phase 3 (US5: Set Expiry)  — BLOCKS Phase 4 (data entry gate)
            ├── Phase 4 (US1: Reminders)   — depends on Phase 3 for meaningful test data
            ├── Phase 5 (US2: Auto-Suspend) — depends on Phase 4 (extends same command)
            ├── Phase 6 (US4: Grace Period) — depends on Phase 5 (override for suspended vendors)
            └── Phase 7 (US3: Dashboard)   — depends on Phases 4+5+6 (all data sources)
Phase 8 (Polish) — depends on all user story phases
```

### User Story Dependencies

| Story | Depends On | Reason |
|---|---|---|
| US5 (Phase 3) | Phase 2 only | First: enables expiry data entry |
| US1 (Phase 4) | Phase 3 | Needs docs with `expires_at` set |
| US2 (Phase 5) | Phase 4 | Extends same `CheckDocumentExpiryCommand` |
| US4 (Phase 6) | Phase 5 | Grace period overrides auto-suspended vendors |
| US3 (Phase 7) | Phase 4+5+6 | Widget queries all compliance data |

### Within Each Phase

- Enum + Model tasks (T006–T009) fully parallel — different files
- `VendorAutoSuspended` event (T017) and `VendorGracePeriodGranted` event (T024) parallelizable with the actions that use them, as long as the event class is written first
- Pest test tasks must follow the implementation tasks they test

### Parallel Opportunities

```bash
# Phase 2 — after T005 (migrate), run these in parallel:
T006: ComplianceEventType enum
T007: VendorComplianceEvent model
T008: VendorDocument model scopes
T009: VendorProfile suspension_reason cast

# Phase 5 — start event before action:
T017: VendorAutoSuspended event   ←── then T018: AutoSuspendForExpiredDocAction

# Phase 6 — start event before action:
T024: VendorGracePeriodGranted event   ←── then T025: GrantDocGracePeriodAction

# Phase 8 — run in parallel:
T033: Schema doc backfill
T034: Phasing plan backfill
```

---

## Implementation Strategy

### MVP (US5 + US1 only — ~3 hours)

1. Complete Phase 1 (ADR) + Phase 2 (schema + models)
2. Complete Phase 3 (US5): Admin can set expiry on docs
3. Complete Phase 4 (US1): Daily reminders fire
4. **STOP and VALIDATE**: Run `pest --group=compliance` — 7 tests green
5. This alone delivers the primary vendor UX value (reminder cascade)

### Full Delivery (all 5 stories — full day)

1. MVP above (Phase 1–4)
2. Phase 5 (US2): Auto-suspension + event cascade
3. Phase 6 (US4): Grace period override
4. Phase 7 (US3): Dashboard widgets + ExpiredDocsResource
5. Phase 8: Pint + PHPStan + full pest suite (16 cases)

---

## Summary

| Phase | Story | Tasks | Tests |
|---|---|---|---|
| 1: Setup | — | 1 | — |
| 2: Foundational | — | 8 | — |
| 3: US5 Set Expiry | US5 (P1) | 3 | 2 cases |
| 4: US1 Reminders | US1 (P1) | 4 | 5 cases |
| 5: US2 Auto-Suspend | US2 (P1) | 7 | 5 cases |
| 6: US4 Grace Period | US4 (P2) | 5 | 4 cases |
| 7: US3 Dashboard | US3 (P2) | 4 | — |
| 8: Polish | — | 3 | — |
| **Total** | | **35 tasks** | **16 Pest cases** |
