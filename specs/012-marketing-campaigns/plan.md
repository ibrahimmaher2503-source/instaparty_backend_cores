# Implementation Plan: Marketing Campaigns (Phase 5.3)

**Branch**: `012-marketing-campaigns` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)
**ADR**: [ADR-0010 — Communication Module](../../docs/adr/0010-communication-module.md) — **Accepted 2026-05-03** (campaigns are a sub-capability of Communication; no new ADR required)

---

## Summary

Build the **Marketing Campaign Builder** under `app/Modules/Communication/` — three new tables (`campaigns`, `campaign_runs`, `campaign_recipients`), one Filament resource (`CampaignResource`), one segment resolver service, one queued dispatch job, and one new domain event. All four channels (push/SMS/WhatsApp-stub/email) reuse the Phase 5.0 `DispatchNotificationAction` and its channel adapters — campaigns are **not** a parallel implementation. The builder is Filament-only (no customer/vendor API). Marketing opt-outs are honored by reusing Phase 5.0's `notification_preferences` enforcement inside `DispatchNotificationAction`. One shipping day, Week 7.

**Depends on:** Phase 5.0 (`DispatchNotificationAction`, FCM/Vonage/WhatsApp-stub/Mailchimp adapters, `notification_preferences` enforcement, `notification_dispatches` append-only ledger). **Blocks:** None.

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12
**Primary Dependencies** (all already installed for Phase 5.0 — no new `composer require`):
- `spatie/laravel-translatable` — `campaigns.subject` and `campaigns.body` JSON columns
- `filament/spatie-laravel-translatable-plugin` — EN/AR tabs in `CampaignResource`
- `bezhansalleh/filament-shield` — auto-generate `view_any_campaign`, `create_campaign`, `update_campaign`, `delete_campaign`, plus a custom `dispatch_campaign` permission
- `kreait/laravel-firebase`, `laravel/vonage-notification-channel`, `netflie/whatsapp-cloud-api`, `mailchimp/marketing` — channel adapters via Phase 5.0's `DispatchNotificationAction` (no direct use here)

**Storage**: MySQL 8 — 3 new tables. None are append-only per `11_DB_Schema.md` (status fields mutate during the run lifecycle).

**Queue**: Redis (existing). Per-recipient dispatch jobs are queued; the Filament "Send now" action returns immediately after enqueueing.

**Testing**: Pest — segment resolution per filter combo (incl. all three product types), per-locale body selection, opt-out skip semantics, all four channel adapters, atomic "Send now" transaction, `MarketingCampaignDispatched` afterCommit. Architecture tests for cross-module imports and product-type string conditionals.

**Target Platform**: Linux (Docker Compose dev, Hetzner CCX13 staging)
**Project Type**: Modular monolith API + Filament admin

**Performance Goals**:
- Segment resolution for 1k-recipient run completes within 60 seconds (SC-002)
- "Send now" action returns within 2 seconds (queues jobs only; resolution + per-recipient dispatch happen async)
- Per-recipient dispatch job p95 under 3 seconds (inherits Phase 5.0 budget)

**Constraints**:
- All segment cross-module reads via `BookingHistoryReader` contract from `Booking/Domain/Contracts/` — **no** direct `Booking\Domain\Models` import
- `event_category` for every Campaign Builder dispatch is hard-coded `'marketing'` (FR-5.3.12)
- Empty `segment_filters` is rejected at form-validation time (no accidental "send to all")
- WhatsApp uses the Phase 5.0 stub adapter — no real Cloud API calls
- Quiet hours are NOT enforced for marketing campaigns (intentional admin-driven send)

---

## 1. ADR Reference

**ADR-0010 — Communication Module** — Accepted 2026-05-03
Path: `docs/adr/0010-communication-module.md`

Phase 5.3 introduces no new architectural decisions. Campaign tables sit inside the Communication module governed by ADR-0010; the Campaign Builder reuses ADR-0010's `NotificationChannelAdapter` contract and `DispatchNotificationAction` without modification.

**No new ADR required.** If implementation surfaces a need (e.g., dedicated segment-CDP read model in Phase 2), file a new ADR then — not now.

---

## 2. Constitution Check

| Principle | Status | Reasoning |
|---|---|---|
| **I. Modular Monolith** | ✅ PASS | All 3 tables, models, Action, Job, and Filament resource live under `app/Modules/Communication/`. Cross-module reads (booking history, governorate filter) go through `Booking\Domain\Contracts\BookingHistoryReader` and `Geography\Domain\Contracts\GovernorateReader` — no Eloquent model imports. |
| **II. Three Product Types** | ✅ PASS | `segment_filters.booked_product_type` and `campaigns.product_type_segment` JSON use `App\Modules\Catalog\Domain\Enums\ProductType`. Pest covers all three (`rental`/`sale`/`digital` groups). No `if/elseif` on type strings — segment resolver uses `match($enum)` when a per-type query branch is needed (none in Phase 1; reserved for Phase 2 if added). |
| **III. Money Discipline** | ✅ N/A | No money columns in this phase. FR-5.3.05 explicitly forbids embedding provider costs in any platform ledger; provider invoices reconcile out-of-band. |
| **IV. Bilingual EN+AR** | ✅ PASS | `campaigns.subject` and `campaigns.body` are JSON translatable columns. Filament form uses translatable plugin EN/AR tabs and validates both bodies are non-empty (FR-5.3.20). Per-recipient locale resolved at run time from `users.preferred_locale` (FR-5.3.10). |
| **V. Append-Only Tables** | ✅ PASS (with note) | `campaigns`, `campaign_runs`, `campaign_recipients` are **not** in the append-only list (`CLAUDE.md` §15). They have `created_at`/`updated_at` per `11_DB_Schema.md` §11. Only documented status fields mutate after creation: `campaigns.status`, `campaign_runs.{started_at, completed_at, recipients_sent, recipients_failed}`, `campaign_recipients.{status, dispatch_id}`. No soft deletes added. |
| **VI. ADR Before Code** | ✅ PASS | Communication module governed by ADR-0010 (Accepted). Campaign tables are explicitly listed under Communication in `11_DB_Schema.md` §11. No new ADR required. |
| **VII. Test-First for Critical Paths** | ✅ PASS | One-day phase; tests written same-day per Constitution §VII. Critical paths covered: segment resolution, per-locale routing, opt-out skip, four-channel adapter dispatch, atomic Send-now transaction, afterCommit event. |
| **VIII. Idempotency** | ✅ PASS (N/A for keys table) | No public payment-mutating endpoint, so no `idempotency_keys` table entry needed. Double-click on "Send now" guarded by Filament `requiresConfirmation()` + status-state check (`running` campaigns reject re-dispatch). Per-recipient `DispatchNotificationAction` calls write a single `notification_dispatches` row per call (Phase 5.0 contract). |
| **IX. Domain Events `DB::afterCommit`** | ✅ PASS | "Send now" Action enqueues per-recipient jobs only via `DB::afterCommit()` after the transaction creating the run + recipients commits. `MarketingCampaignDispatched` event fires via `DB::afterCommit()` on run completion (FR-5.3.23). |
| **X. Vendor Approval Gate** | ✅ N/A | Phase 1 campaigns target customers only (`audience = customer` is implicit). |
| **XI. Document Storage** | ✅ N/A | No file uploads. Image attachments cut-listed to Phase 1.5. |

**GATE RESULT: ALL PASS — proceed to implementation.**

---

## 3. Schema

Matches `docs/specs/11_DB_Schema.md` §11 exactly. Migrations run in FK dependency order:

| # | Migration filename | Tables / Changes |
|---|---|---|
| 1 | `2026_05_07_100001_create_campaigns_table.php` | `campaigns` — `subject` + `body` JSON translatable; ENUM channel/target_locale/status; `created_by` FK→users |
| 2 | `2026_05_07_100002_create_campaign_runs_table.php` | `campaign_runs` — FK → `campaigns.id` RESTRICT |
| 3 | `2026_05_07_100003_create_campaign_recipients_table.php` | `campaign_recipients` — FK → `campaign_runs.id` CASCADE, FK → `users.id` RESTRICT, FK → `notification_dispatches.id` RESTRICT (nullable) |

### `campaigns` (migration 1)

```
id                    BIGINT UNSIGNED PK AUTO_INCREMENT
public_id             CHAR(26) UNIQUE NOT NULL                       ULID
name                  VARCHAR(160) NOT NULL                          internal admin label
channel               ENUM('push','sms','whatsapp','email') NOT NULL
target_locale         ENUM('ar','en','both') NOT NULL
segment_filters       JSON NOT NULL                                  e.g. {"booked_product_type":"rental","booked_within_days":30}
product_type_segment  JSON NULL                                      optional ["rental","sale"] (alternate to segment_filters key)
subject               JSON NULL                                      {"en":"...", "ar":"..."}
body                  JSON NOT NULL                                  {"en":"...", "ar":"..."}
scheduled_at          TIMESTAMP NULL                                 column exists per schema; Phase 1 form hides it (cut-listed)
status                ENUM('draft','scheduled','running','completed','failed','cancelled') NOT NULL DEFAULT 'draft'
created_by            BIGINT UNSIGNED NOT NULL FK→users.id RESTRICT
created_at            TIMESTAMP
updated_at            TIMESTAMP

INDEX (status, created_at)
INDEX (channel, status)
INDEX (created_by, status)
```

### `campaign_runs` (migration 2)

```
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
campaign_id         BIGINT UNSIGNED NOT NULL FK→campaigns.id RESTRICT
started_at          TIMESTAMP NULL
completed_at        TIMESTAMP NULL
recipients_total    INT UNSIGNED NOT NULL DEFAULT 0
recipients_sent     INT UNSIGNED NOT NULL DEFAULT 0
recipients_failed   INT UNSIGNED NOT NULL DEFAULT 0
created_at          TIMESTAMP
updated_at          TIMESTAMP

INDEX (campaign_id, started_at)
```

> **Skipped count is derived** at read time: `recipients_total - recipients_sent - recipients_failed` = skipped + still-queued. Phase 1 does not add a separate `recipients_skipped` column (matches `11_DB_Schema.md` §11 exactly).

### `campaign_recipients` (migration 3)

```
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
campaign_run_id     BIGINT UNSIGNED NOT NULL FK→campaign_runs.id CASCADE
user_id             BIGINT UNSIGNED NOT NULL FK→users.id RESTRICT
dispatch_id         BIGINT UNSIGNED NULL FK→notification_dispatches.id RESTRICT
status              ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued'
created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

INDEX (campaign_run_id, status)
INDEX (user_id, created_at)
UNIQUE (campaign_run_id, user_id)              prevents duplicate recipient rows within one run
```

> The `UNIQUE (campaign_run_id, user_id)` index is added on top of `11_DB_Schema.md` §11 to enforce the dedupe rule from FR-5.3 segment resolver (matches the spec's "deduplicated by `user_id`" requirement). It does not contradict the schema — the schema lists indexes as a minimum, not a maximum.

**FK dependency order:** `users`, `notification_dispatches` (Phase 5.0) → `campaigns` → `campaign_runs` → `campaign_recipients`

**Critical indexes:**
- `campaigns (status, created_at)` — admin "running campaigns" view
- `campaign_runs (campaign_id, started_at)` — campaign detail page
- `campaign_recipients (campaign_run_id, status)` — per-status counts on campaign detail page
- `campaign_recipients UNIQUE (campaign_run_id, user_id)` — dedupe guarantee

---

## 4. Per-type Coverage

Marketing campaign dispatch is **cross-type infrastructure** — the segment filter is parameterized over `ProductType`, not branched per type.

| Filter shape | Pest group(s) | Test |
|---|---|---|
| `{"booked_product_type": "rental", "booked_within_days": 30}` | `rental` | "resolves segment for rental within 30 days" |
| `{"booked_product_type": "sale", "booked_within_days": 30}` | `sale` | "resolves segment for sale within 30 days" |
| `{"booked_product_type": "digital", "booked_within_days": 30}` | `digital` | "resolves segment for digital within 30 days" |
| `{"booked_within_days": 30}` (no type filter) | `rental, sale, digital` | "resolves segment across all product types" |
| `{"governorate_id": 5, "booked_product_type": "rental"}` | `rental` | "resolves segment by governorate × product type" |

**Rule:** `SegmentResolver` consumes `App\Modules\Catalog\Domain\Enums\ProductType` and uses a single parameterized query — `match($enum)` is reserved for any future per-type query branch but is not needed in Phase 1 because the booking-history reader query is uniform across types (filters `booking_items.product_type` column).

```php
// SegmentResolver::resolve() pattern (simplified)
public function resolve(array $filters): Collection // <user_id>
{
    return $this->bookingHistoryReader->customersWithBookingsMatching(
        productType: isset($filters['booked_product_type'])
            ? ProductType::from($filters['booked_product_type'])
            : null,
        withinDays: $filters['booked_within_days'] ?? null,
        governorateId: $filters['governorate_id'] ?? null,
        preferredLocale: $filters['preferred_locale'] ?? null,
    );
}
```

**Pest coverage:** every per-type test runs the canonical "10% off Rentals" template variant adapted for that type (`'10% off Sales this week'`, `'10% off Digital this week'`) and asserts the resulting `campaign_recipients` set matches the seeded customers of that type only.

---

## 5. Locale Coverage

**Translatable fields in this phase:**

| Table | Column | Type | Required locales | Validation |
|---|---|---|---|---|
| `campaigns` | `subject` | JSON `{"en":"...", "ar":"..."}` | EN + AR both required when channel ∈ {email}; nullable otherwise (push/SMS/WA have no subject) | conditional `required_array_keys:en,ar` per `channel` |
| `campaigns` | `body` | JSON `{"en":"...", "ar":"..."}` | EN + AR both required (FR-5.3.20) | `required_array_keys:en,ar` + `min:1` per locale |

**Per-recipient locale resolution (FR-5.3.10):**

1. `DispatchCampaignRecipientJob` reads target `User` via `BookingHistoryReader` (returns `user_id` set; jobs hydrate the User aggregate from Identity contract).
2. Reads `$user->preferred_locale` (ENUM `'ar'|'en'`).
3. Calls `DispatchNotificationAction` with the resolved locale; the Action passes through to `TemplateResolver` from Phase 5.0.
4. **For campaigns**, the body comes from the campaign row, not from `notification_templates`. The job pulls `$campaign->getTranslation('body', $userLocale)` (spatie/laravel-translatable) and passes it to `DispatchNotificationAction` via a synthetic template payload (event_key = `marketing.campaign.{campaign.public_id}`).
5. `notification_dispatches.locale` is written to the resolved per-recipient locale.

**`target_locale` filter behavior:**

- `target_locale = 'ar'` → segment excludes users with `preferred_locale = 'en'`
- `target_locale = 'en'` → segment excludes users with `preferred_locale = 'ar'`
- `target_locale = 'both'` → no exclusion; each user receives their preferred-locale body

**Filament locale coverage:**

- `CampaignResource` uses the `Translatable` trait + `filament/spatie-laravel-translatable-plugin`
- EN/AR tabs at the top of the form for `subject` and `body`
- Save validation rejects if either locale's `body` is empty

---

## 6. Idempotency

| Surface | Idempotency-Key? | Reason |
|---|---|---|
| Filament Campaign Builder "Save Draft" action | No | Pure UPSERT keyed by `campaigns.public_id` (or admin-typed `name` on creation); no financial mutation |
| Filament Campaign Builder "Send Now" action | Status-guard, not key-based | Action checks `$campaign->status === 'draft'`; if already `running/completed/cancelled`, rejects with notification. Filament `requiresConfirmation()` blocks rapid double-click. |
| `DispatchCampaignRecipientJob` (per-recipient queued job) | UNIQUE-index guard | `campaign_recipients UNIQUE (campaign_run_id, user_id)` prevents duplicate row insert if the job re-runs. The job is wrapped in `DB::transaction` and uses `INSERT IGNORE` semantics for the recipient row — re-runs become no-ops. |
| `DispatchNotificationAction` (called by the job) | Inherited from Phase 5.0 | Already idempotent within a queue retry window — writes one dispatch row per call, no duplicate state. |

**No `idempotency_keys` table entries for Phase 5.3.** Per `CLAUDE.md` §11 + Constitution §VIII, the `idempotency_keys` mandate applies to **payment-mutating endpoints** — campaigns are admin-side state changes guarded by status transitions and unique indexes.

---

## 7. Domain Events

### Events Phase 5.3 publishes

| Event | When | Fired via | Payload |
|---|---|---|---|
| `Communication\Events\MarketingCampaignDispatched` | After `campaign_runs.completed_at` is set and `campaigns.status` transitions to `completed` | `DB::afterCommit(fn () => MarketingCampaignDispatched::dispatch(...))` inside `CompleteCampaignRunAction::execute()` | `campaign_id`, `campaign_run_id`, `recipients_total`, `recipients_sent`, `recipients_failed` |

```php
// Pattern in CompleteCampaignRunAction::execute()
return DB::transaction(function () use ($run) {
    $run->update(['completed_at' => now()]);
    $run->campaign->update(['status' => CampaignStatus::Completed]);
    DB::afterCommit(fn () => MarketingCampaignDispatched::dispatch(
        $run->campaign_id, $run->id, $run->recipients_total,
        $run->recipients_sent, $run->recipients_failed,
    ));
    return $run;
});
```

### Events Phase 5.3 consumes

None. Phase 5.3 is a self-contained admin tool — it does not listen to Booking/Payment events. (Future Phase 8.3 Offer Governance may consume `MarketingCampaignDispatched` for offer-attribution analytics.)

### Per-recipient job event hygiene

`DispatchCampaignRecipientJob` reuses `DispatchNotificationAction`, which already fires `NotificationDispatched` via `DB::afterCommit()` per Phase 5.0. No additional event from the job itself — keeping the per-recipient event surface single-sourced from Phase 5.0.

---

## 8. API Documentation Plan

**Phase 5.3 has no public API endpoints.** Campaign management is entirely Filament-internal. Customer-side opt-out is already exposed by the Phase 5.0 `notification-preferences` endpoints.

| Method | Path | Auth | Purpose |
|---|---|---|---|
| *(none)* | *(none)* | *(N/A)* | All workflows live in Filament admin under Communications → Campaigns |

**`api-registry.md` update:** No new entries.
**Bruno/Postman collections:** No new files. (Phase 5.0's `communication.bru` already covers customer opt-out endpoints used by this phase's marketing-skip path.)

**Filament resource path:** `app/Modules/Communication/Filament/Resources/CampaignResource.php`. Permissions generated by `php artisan shield:generate --all` after the resource is added.

---

## 9. Packages Used

All packages are already installed for Phase 5.0 — **no new `composer require`**.

| Package | `10_Package_List.md` §ref | Usage in Phase 5.3 |
|---|---|---|
| `spatie/laravel-translatable:^6.8` | §2 Catalog & Translatable Content | `campaigns.subject` and `.body` JSON translatable columns |
| `bezhansalleh/filament-shield` | §3 Filament Plugins | `CampaignResource` permissions + custom `dispatch_campaign` |
| `filament/spatie-laravel-translatable-plugin` | §3 Filament Plugins | EN/AR tabs in `CampaignResource` |
| `kreait/laravel-firebase:^5.10` | §2 Real-time & Chat / Notifications | FCM push (via Phase 5.0 `FcmPushAdapter`) |
| `laravel/vonage-notification-channel:^3.3` | §2 Notifications | SMS (via Phase 5.0 `VonageSmsAdapter`) |
| `netflie/whatsapp-cloud-api:^1.5` | §2 Notifications | WhatsApp stub (via Phase 5.0 `WhatsAppStubAdapter`) |
| `mailchimp/marketing:^3.0` | §2 Notifications | Email transactional send (via Phase 5.0 `MailchimpEmailAdapter`) |
| `spatie/laravel-permission:^6.10` | §2 Identity & Authorization | Role checks in Filament (`super_admin`, `marketing_admin`) |

> All four channel adapters are reused from Phase 5.0 by way of `DispatchNotificationAction` — Phase 5.3 does **not** instantiate or reference them directly.

---

## 10. Architecture Tests

Add to `tests/Architecture/`:

### Extended: `NoCrossModuleModelImportsTest.php` — Communication section

Phase 5.0 already added a Communication clause; Phase 5.3 verifies the campaign sub-package adheres to the same rule:

```php
test('Communication campaigns do not import other modules\' Eloquent models')
    ->expect('App\Modules\Communication\Application\Actions')
    ->not->toUse([
        'App\Modules\Booking\Domain\Models',
        'App\Modules\Catalog\Domain\Models',
        'App\Modules\Identity\Domain\Models',
        'App\Modules\Geography\Domain\Models',
        'App\Modules\Settlement\Domain\Models',
    ]);
```

### Extended: `NoIfElseOnProductTypeStringTest.php` — Communication

```php
test('SegmentResolver does not branch on product_type strings')
    ->expect('App\Modules\Communication\Application\Services\SegmentResolver')
    ->not->toHaveCode("if.*product_type.*===")
    ->not->toHaveCode("elseif.*product_type.*===");
```

### Extended: `EventAfterCommitTest.php`

```php
test('CompleteCampaignRunAction fires MarketingCampaignDispatched after DB commit')
    ->expect('App\Modules\Communication\Application\Actions\CompleteCampaignRunAction')
    ->toHaveMethod('execute')
    ->not->toUse('Illuminate\Support\Facades\Event::dispatch'); // raw dispatch forbidden — must use DB::afterCommit
```

### Extended: `AppendOnlyTablesHaveNoSoftDeletesTest.php`

Negative assertion that the three new models do **not** add `SoftDeletes`:

```php
test('Campaign models do not use soft deletes')
    ->expect([
        'App\Modules\Communication\Domain\Models\Campaign',
        'App\Modules\Communication\Domain\Models\CampaignRun',
        'App\Modules\Communication\Domain\Models\CampaignRecipient',
    ])
    ->not->toUse('Illuminate\Database\Eloquent\SoftDeletes');
```

### New: `CampaignDispatchUsesPhase50ContractTest.php`

```php
test('DispatchCampaignRecipientJob delegates to DispatchNotificationAction')
    ->expect('App\Modules\Communication\Application\Jobs\DispatchCampaignRecipientJob')
    ->toUse('App\Modules\Communication\Application\Actions\DispatchNotificationAction')
    ->not->toUse([
        'App\Modules\Communication\Infrastructure\Adapters\FcmPushAdapter',
        'App\Modules\Communication\Infrastructure\Adapters\VonageSmsAdapter',
        'App\Modules\Communication\Infrastructure\Adapters\WhatsAppStubAdapter',
        'App\Modules\Communication\Infrastructure\Adapters\MailchimpEmailAdapter',
    ]);
```

> Enforces FR-5.3.18: campaigns must not bypass the Phase 5.0 dispatch contract.

---

## 11. Cut-list (inherited from `docs/specs/09_Phasing_Plan.md` §PHASE 5.3)

| Feature | Decision | Target |
|---|---|---|
| A/B testing — variants, holdout groups, lift measurement | Deferred | Phase 2 |
| Scheduled future sends | Deferred — `campaigns.scheduled_at` column exists; Filament form hides it (Send-now only) | Phase 1.5 |
| Recurring campaigns (weekly/monthly drip) | Deferred — no scheduler in Phase 1 | Phase 1.5 |
| WhatsApp real Cloud API send | Deferred — Phase 1 uses `WhatsAppStubAdapter`; `provider = 'whatsapp_stub'` | Phase 1.5 |
| Mailchimp list / segment sync | Deferred — campaigns dispatch via Mailchimp transactional only | Phase 1.5 |
| Image attachments / rich HTML email templates | Deferred — Phase 1 bodies are plain text + minimal placeholders | Phase 1.5 |
| Quiet-hours suppression for marketing | Deferred — transactional already respects quiet hours; marketing intentionally bypasses (Send-now is admin-driven) | Phase 1.5 |
| Auto-recovery of orphaned runs (queue worker crash mid-run) | Deferred — manual admin "Retry queued recipients" action only | Phase 1.5 |
| "Send to all customers" segment | Deferred — Phase 1 requires non-empty `segment_filters` to prevent accidental blasts (FR-5.3.07) | Phase 1.5 |
| Vendor-targeted campaigns | Deferred — Phase 1 audience is hard-coded to `customer` | Phase 1.5 |
| Offer Governance UI on top of campaigns | Deferred — listens to `MarketingCampaignDispatched` for offer attribution | Phase 8.3 |
| Marketing CDP / denormalized segment read model | Deferred — Phase 1 customer base is small enough for live `IN(...)` queries | Phase 2 |

---

## Implementation Order (Day 1 — single-day phase)

### Morning — Schema + Models + Resolver

1. [T001] Migration 1: `campaigns` (translatable subject/body, status enum, FK created_by)
2. [T002] Migration 2: `campaign_runs` (FK campaign, status counts)
3. [T003] Migration 3: `campaign_recipients` (FK campaign_run, FK user, FK dispatch nullable, UNIQUE(run, user))
4. [T004] Enum: `App\Modules\Communication\Domain\Enums\CampaignStatus` (draft/scheduled/running/completed/failed/cancelled)
5. [T005] Enum: `App\Modules\Communication\Domain\Enums\CampaignChannel` (push/sms/whatsapp/email)
6. [T006] Enum: `App\Modules\Communication\Domain\Enums\CampaignTargetLocale` (ar/en/both)
7. [T007] Enum: `App\Modules\Communication\Domain\Enums\CampaignRecipientStatus` (queued/sent/failed/skipped)
8. [T008] Model: `Campaign` — translatable subject/body, casts, scopes (`scopeRunning`, `scopeDraft`)
9. [T009] Model: `CampaignRun` — relationships, no business logic
10. [T010] Model: `CampaignRecipient` — relationships, status enum cast
11. [T011] Contract: `Booking\Domain\Contracts\BookingHistoryReader` (interface + Eloquent impl in Booking module)
12. [T012] Contract: `Geography\Domain\Contracts\GovernorateReader` (verify exists from Phase 0)
13. [T013] Service: `Communication\Application\Services\SegmentResolver` — accepts validated filter array, returns `Collection<int>` of `user_id`s, deduped, with banned/suspended exclusion + `target_locale` exclusion + `notification_preferences` opt-out filter (joined for skip-vs-eligible distinction at recipient-row write time, not at filter time)

### Midday — Send-Now Action + Job + Event

14. [T014] DTO: `Communication\Application\DTOs\BuildCampaignDTO` and `DispatchCampaignDTO`
15. [T015] Action: `CreateCampaignAction` (draft state)
16. [T016] Action: `DispatchCampaignAction` — orchestrates `DB::transaction`: flip status to `running`, create `campaign_runs` row, resolve segment, write `campaign_recipients` rows in `queued` state, queue `DispatchCampaignRecipientJob` per recipient via `DB::afterCommit()`
17. [T017] Job: `DispatchCampaignRecipientJob` (`ShouldQueue`) — fetches campaign + user, applies opt-out check (`notification_preferences (channel, 'marketing') is_enabled`), if skipped updates recipient row to `skipped` with no dispatch; else calls `DispatchNotificationAction` and links the resulting `notification_dispatches.id` to `campaign_recipients.dispatch_id`, updates recipient `status` to `sent` or `failed`. Wrapped in `DB::transaction` + `INSERT IGNORE` semantics on the recipient row (no-op on retry).
18. [T018] Action: `CompleteCampaignRunAction` — checks for terminal recipient states; if all done, sets `completed_at`, transitions `campaigns.status` to `completed`, fires `MarketingCampaignDispatched` via `DB::afterCommit()`. Called from the per-recipient job after each terminal update.
19. [T019] Action: `CancelCampaignAction` — draft → delete; running → `cancelled` (prevents new job enqueueing; in-flight jobs complete normally per FR-5.3.22)
20. [T020] Event: `Communication\Events\MarketingCampaignDispatched`

### Afternoon — Filament + Permissions + Tests

21. [T021] Filament: `CampaignResource` (form: name, channel, target_locale, segment_filters key-value with whitelisted keys, EN/AR subject + body tabs, Send-now action with confirmation modal)
22. [T022] Filament: `CampaignResource\Pages\ViewCampaign` — recipient sub-table with status filter (paginated) per FR-5.3.21; shows `recipients_total/sent/failed/skipped` reconciliation per SC-006
23. [T023] Filament action: "Send Now" (calls `DispatchCampaignAction::execute()`) — visible only on `draft` campaigns; `requiresConfirmation()`; gated by `dispatch_campaign` permission
24. [T024] Filament action: "Cancel" (draft and running) — calls `CancelCampaignAction::execute()`
25. [T025] Filament action: "Retry queued recipients" (running/cancelled with stuck queued rows) — re-enqueues jobs for those recipient rows
26. [T026] Run `php artisan shield:generate --all`; confirm `dispatch_campaign` permission seeded; assign to `super_admin` and `marketing_admin` roles in `RolePermissionsSeeder`
27. [T027] Pest: `DispatchCampaignActionTest` (segment resolution per all 3 product types, locale routing, opt-out skip, four-channel dispatch, atomic transaction, afterCommit event)
28. [T028] Pest: `SegmentResolverTest` — five filter combos in §4 above
29. [T029] Pest: `CampaignFilamentResourceTest` — admin-only access, EN+AR validation, Send-now status guard, double-click no-op
30. [T030] Architecture tests (§10 above)
31. [T031] Update `tests/Feature/Modules/Communication/Campaigns/` directory + Pest groups: `communication`, `campaign`, plus `rental | sale | digital` per per-type test
32. [T032] Smoke test in dev: build "10% off Rentals" canonical campaign per `09_Phasing_Plan.md` deliverable; verify recipients resolve, opt-outs skip, all four channels write `notification_dispatches` rows with correct `provider`

**Exit criteria check at end of day** (per `09_Phasing_Plan.md` §PHASE 5.3):
- ✅ Admin builds campaign for specific segment
- ✅ All 4 channels dispatch (verified by Pest per channel)
- ✅ Locale respected per recipient (verified by Pest assertion on `notification_dispatches.locale = users.preferred_locale`)
