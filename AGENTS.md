# AGENTS.md — InstaParty Backend (Codex CLI)

> Loaded by Codex CLI at every session start. Treat as the project's operating contract for autonomous agents. When this file conflicts with chat instructions, **this file wins** unless Ibrahim explicitly overrides in conversation.
>
> Companion docs: `CLAUDE.md` (Claude Code memory), `.specify/memory/constitution.md` (spec-kit constitution). All three agree on the rules below.

---

## 1. Project Identity

| Field | Value |
|---|---|
| **Name** | InstaParty — multi-vendor event-commerce marketplace (birthdays, weddings, engagements) |
| **Stack** | Laravel 12 + Filament v3 + MySQL 8 + Redis |
| **Phase** | Phase 1 (8 weeks, **26 micro-phases** ≤3 days each — see `docs/specs/09_Phasing_Plan.md`) |
| **Languages** | **Arabic (primary, RTL)** + **English (secondary, LTR)** — both mandatory from day 1 |
| **Owner** | Ibrahim, solo full-stack, Benha, Egypt |
| **Repo** | `instaparty-backend` (Laravel API + Filament admin) |
| **Sister repos** | `instaparty-mobile` (Flutter, Phase 2), `instaparty-web` (Next.js, Phase 1.5) |

---

## 2. Source of Truth Hierarchy

When artifacts disagree, resolve in this order (top wins):

1. `docs/specs/01_PRD.md` — Phase 1 functional requirements (FR-1…FR-30, BR-1…BR-6)
2. `.specify/memory/constitution.md` — spec-kit constitution
3. `CLAUDE.md` — Claude Code project memory (mirrors this file's rules)
4. **`AGENTS.md`** (this file)
5. `docs/specs/02_Tech_Decisions.md` — locked stack
6. `docs/specs/03_Three_Product_Types.md` — rental/sale/digital matrix
7. `docs/specs/04_Bilingual_Spec.md` — translatable fields and locale rules
8. `docs/specs/11_DB_Schema.md` — locked schema (60 tables, 13 modules)
9. `docs/specs/09_Phasing_Plan.md` — 26 micro-phases
10. `docs/specs/10_Package_List.md` — approved packages
11. `docs/adr/*.md` — module-level decisions (ADR-0001 onward)
12. `specs/NNN-feature/spec.md` → `plan.md` → `tasks.md` (active feature)
13. Conversation

If any conflict surfaces during work, name it explicitly and cite the document — do not silently pick a winner.

---

## 3. Locked Tech Stack

The stack below is **locked**. Replacing any choice requires an ADR + amendment of `docs/specs/02_Tech_Decisions.md` before code changes.

| Layer | Choice | Forbidden alternatives |
|---|---|---|
| Framework | Laravel 12 (PHP 8.3+) | (locked) |
| Pattern | Modular Monolith (`app/Modules/{Name}/`) | Microservices, package-per-module, single-folder |
| Admin UI | Filament v3 at `/admin` | Backpack, Nova, custom panel |
| Database | MySQL 8 / MariaDB 11, `utf8mb4` / `utf8mb4_unicode_ci` | PostgreSQL (Phase 2 maybe), SQLite for prod |
| Cache + Queue | Redis (predis) | Memcached, database queue, file cache for prod |
| Auth | Laravel Sanctum — SPA cookies (Next.js) + tokens (Flutter) | Passport, JWT, custom auth |
| Authorization | `spatie/laravel-permission` (per-product-type vendor scopes) | Bouncer, custom Gates only |
| Search | Meilisearch via Laravel Scout | Algolia, Typesense, ElasticSearch, MySQL FULLTEXT |
| Translatable | `spatie/laravel-translatable` JSON columns (EN+AR mandatory) | astrotomic, separate `name_en`/`name_ar` columns, custom JSON casts |
| Money | `brick/money`, integer minor units (`{field}_minor` BIGINT + `{field}_currency` CHAR(3)) | `moneyphp/money`, DECIMAL columns, FLOAT, raw arithmetic |
| State machines | `spatie/laravel-model-states` | Symfony Workflow, custom enum-only state, finite-state-machine packages |
| Activity log | `spatie/laravel-activitylog` + `audit_logs` table | telescope alone, custom logging only |
| Excel | `maatwebsite/excel` | openspout directly, csv hand-rolled, fast-excel |
| File storage | DigitalOcean Spaces (prod) / MinIO (dev) via S3 driver | Local disk for prod, FTP, GCS, raw R2 |
| Realtime | Laravel Reverb (business events) + Firebase (chat, push) | Pusher (cost), Soketi prod (dev OK), Ably |
| Push notifications | Firebase Cloud Messaging | OneSignal, custom APNs/FCM integration |
| Payment gateway | Paymob (Phase 1 primary) via `PaymentGateway` interface | Stripe (no MENA), Tabby/Tamara/HyperPay (Phase 2) |
| ULIDs | Native `Str::ulid()` + `HasUlids` trait | `robinvdvleuten/ulid`, UUID v4, hashids |
| Identifier exposure | Internal `id` BIGINT, external `public_id` CHAR(26) ULID in URLs | Exposing internal `id` in any API URL |
| Server timezone | UTC always; convert at API Resource layer | Local timezone in DB, conversion in business logic |

---

## 4. Phase 1 Forbidden Features

If a request, spec, or task suggests any of these, **refuse and reference PRD §5.2 + §11**:

- ❌ Vendor subscription tiers (silver/gold/bronze, plan-based privileges)
- ❌ Platform-owned package products with separate accounting/commission rules
- ❌ Dispute-resolution engine with automated penalties/compensation
- ❌ Per-category visual card-template system managed by admin
- ❌ Vendor page slider module
- ❌ Vendor QR / barcode direct catalog feature
- ❌ Advanced tax invoicing beyond foundational structural readiness
- ❌ Multi-currency activation (schema is ready; activation is Phase 2)
- ❌ GCC payment gateway adapters (Tabby, Tamara, HyperPay) — Phase 2
- ❌ Hijri calendar
- ❌ Frontend framework in this repo (Next.js lives in `instaparty-web`)

When pushed back, name the spec section and propose deferral to Phase 1.5 / Phase 2 instead of silently building.

---

## 5. Approved Packages Only

You may **only** `composer require` packages from this list. Anything else requires Ibrahim's explicit approval **and** a same-commit update to `docs/specs/10_Package_List.md`.

### 5.1 Composer — Foundation
- `laravel/framework:^12.0`
- `laravel/sanctum:^4.0`
- `laravel/scout:^10.10`
- `laravel/reverb:^1.0`
- `predis/predis:^2.2`

### 5.2 Composer — Identity & Authorization
- `spatie/laravel-permission:^6.10`
- `pragmarx/google2fa:^8.0`
- `bacon/bacon-qr-code:^3.0`

### 5.3 Composer — Catalog & Translatable Content
- `spatie/laravel-translatable:^6.8`
- `spatie/laravel-medialibrary:^11.9`
- `spatie/laravel-tags:^4.6`

### 5.4 Composer — Search
- `meilisearch/meilisearch-php:^1.10`

### 5.5 Composer — Money
- `brick/money:^0.10` (never substitute `moneyphp/money`)

### 5.6 Composer — Booking & State Machines
- `spatie/laravel-model-states:^2.7`
- `spatie/laravel-data:^4.13`

### 5.7 Composer — Payments
- `guzzlehttp/guzzle:^7.9`
- `symfony/http-client:^7.1`
- (Paymob is integrated by hand in `Modules/Payments/Infrastructure/Gateways/PaymobGateway.php` — no SDK)

### 5.8 Composer — Storage
- `league/flysystem-aws-s3-v3:^3.29`

### 5.9 Composer — Realtime & Chat
- `kreait/laravel-firebase:^5.10`
- `pusher/pusher-php-server:^7.2`

### 5.10 Composer — Notifications (multi-channel)
- `laravel-notification-channels/fcm:^4.5`
- `laravel/vonage-notification-channel:^3.3`
- `netflie/whatsapp-cloud-api:^1.5`
- `mailchimp/marketing:^3.0`

### 5.11 Composer — Excel
- `maatwebsite/excel:^3.1`

### 5.12 Composer — Audit & Backup
- `spatie/laravel-activitylog:^4.9`
- `spatie/laravel-backup:^9.2`

### 5.13 Composer — Filament v3 (curated plugins only)
- `filament/filament:^3.2`
- `bezhansalleh/filament-shield:^3.3`
- `filament/spatie-laravel-translatable-plugin:^3.2`
- `filament/spatie-laravel-media-library-plugin:^3.2`
- `filament/spatie-laravel-tags-plugin:^3.2`
- `filament/spatie-laravel-settings-plugin:^3.2`
- `filament/spatie-laravel-activitylog-plugin:^3.2`
- `awcodes/filament-tiptap-editor:^3.4`
- `bezhansalleh/filament-language-switch:^3.1`
- `pxlrbt/filament-excel:^2.4`
- `saade/filament-fullcalendar:^3.2`

> **Filament plugin sprawl is the #1 way to lose Phase 1.** No additional Filament plugins without amending `10_Package_List.md`.

### 5.14 Composer — Dev / Quality
- `pestphp/pest:^3.5`
- `pestphp/pest-plugin-laravel:^3.0`
- `pestphp/pest-plugin-arch:^3.0`
- `laravel/pint:^1.18`
- `larastan/larastan:^3.0`
- `nunomaduro/collision:^8.5`
- `fakerphp/faker:^1.23`
- `mockery/mockery:^1.6`
- `spatie/laravel-ignition:^2.8`
- `laravel/telescope:^5.2` (dev only — disabled in production)
- `driftingly/rector-laravel:^2.0`

### 5.15 NPM — Build Tooling
- `vite`, `laravel-vite-plugin`
- `tailwindcss`, `@tailwindcss/forms`, `@tailwindcss/typography`
- `postcss`, `autoprefixer`

> No frontend framework in this repo. Next.js lives in `instaparty-web`.

---

## 6. Architectural Constraints

### 6.1 Modular Monolith — `app/Modules/{Name}/`

Every feature lives under `app/Modules/{ModuleName}/` with this exact layer layout:

```
app/Modules/{Name}/
├── Domain/         # Models (relationships/casts/scopes ONLY), Enums, Events, States, Contracts
├── Application/    # Actions (one execute() method), Services, DTOs, Listeners
├── Infrastructure/ # Repositories, Gateways
├── Http/           # Controllers (3-line bodies), Requests, Resources, Middleware
├── Filament/       # Resources, Pages, Widgets (auto-discovered)
├── Routes/         # customer.php, vendor.php, admin.php
├── Database/       # Migrations, Factories, Seeders (NOT in root database/migrations/)
├── Resources/lang/{en,ar}/
└── Providers/{Name}ServiceProvider.php
```

Phase 1 modules: **Identity, Catalog, Discovery, Booking, Negotiation, Payments, Settlement, Reviews, Communication, Reporting, Geography, Loyalty, Shared.**

### 6.2 Cross-module communication — domain events or Contracts only

- ✅ Communicate via **domain events** (preferred) or **public interfaces** in `Domain/Contracts/`.
- ✅ The producing module binds its Contract → Eloquent implementation in its ServiceProvider.
- ❌ **Never** import another module's Eloquent Model directly across module boundaries.
- ❌ **Never** instantiate another module's Action class directly when an event would suffice.
- An architecture test in `tests/Architecture/NoCrossModuleModelImportsTest.php` enforces this.

### 6.3 Three product types — `match($enum)`, NEVER `if/elseif`

- Canonical enum: `App\Modules\Catalog\Domain\Enums\ProductType` (cases `Rental`, `Sale`, `Digital`).
- Per-type Form Requests, Actions, API Resources, Filament Resources, slot resolvers, price calculators, fulfillment state machines.
- Cross-type code uses **PHP `match($enum)`** — never if/elseif chains on type strings.
- Storage: polymorphic base `services` (with `product_type` ENUM discriminator) + 1:1 detail tables `service_rental_details`, `service_sale_details`, `service_digital_details`. Single Table Inheritance and three independent top-level tables are both **forbidden**.
- Tests for any type-aware feature MUST cover all three types (`it('...rental...')`, `it('...sale...')`, `it('...digital...')`).

### 6.4 Money — BIGINT minor units, never float

- Every money column is `BIGINT UNSIGNED` named `{field}_minor` paired with `CHAR(3)` `{field}_currency`.
- Cast through `App\Modules\Shared\Domain\Casts\MoneyCast` → returns `Brick\Money\Money` instances.
- All arithmetic via `Brick\Money\Money` methods (`plus`, `minus`, `multipliedBy`, `dividedBy` with rounding mode).
- ❌ `float` / `decimal` / `double` for money — anywhere — is **forbidden**.
- ❌ Raw `+ - * /` operators on money values are **forbidden**.
- Commission rates stored as integer basis points (10000 = 100%). Display formatting via locale-aware `Money::formatTo($locale)`.
- Architecture test `tests/Architecture/NoFloatForMoneyTest.php` enforces this.

### 6.5 Bilingual EN+AR — both required for every translatable field

- Translatable fields use **JSON columns** + `spatie/laravel-translatable` (`protected $translatable = [...]`).
- Both `en` and `ar` keys MUST be present and non-empty — empty strings fail Form Request validation.
- API Resources convert to `App::getLocale()` for default response (single-locale).
- Admin/vendor edit screens may request multi-locale via `?translations=all`.
- Filament Resources use the translatable plugin with EN/AR tabs side-by-side at the top of the form.
- Validation error messages must be localized (`resources/lang/{locale}/validation.php` per module).
- Tests assert response shape in **both** locales for any user-facing endpoint.
- RTL hinting: API responses include `direction: 'rtl' | 'ltr'` when locale is set; Filament must render correctly in AR.

### 6.6 Append-only tables — no `softDeletes`, no UPDATE except status

| Table | Mutable columns |
|---|---|
| `wallet_ledger` | (none — fully immutable) |
| `audit_logs` | (none) |
| `payments` | `status`, `gateway_response_log` only |
| `commissions` | `status` only |
| `withdrawals` | `status` only |
| `booking_state_transitions` | (none) |
| `event_outbox` | `published_at`, `published_attempts` only |
| `analytics_events` | (none) |
| `loyalty_ledger` | (none) |
| `booking_snapshots` | (none) |
| `chat_message_log` | (none) |
| `search_logs` | (none) |
| `payment_attempts` | (none) |

Migrations for these tables MUST NOT include `softDeletes()` or `updated_at`. A wallet credit is reversed by a counter-entry, never by editing the original row. Architecture test `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` enforces this.

### 6.7 Soft-deleted tables (allow `softDeletes()` and `deleted_at`)

`users`, `vendor_profiles`, `services`, `bookings`, `service_reviews`, `vendor_reviews`, `categories`, `occasions`, `customer_addresses`. All others should NOT have soft deletes.

### 6.8 Other inviolable rules

- **Thin controllers.** Action body max 3 lines. Real work goes into Action classes.
- **Fat single-purpose Actions** with one public `execute()` method, constructor injection, mutations wrapped in `DB::transaction`.
- **Models hold ONLY** relationships, casts, scopes — never business logic.
- **Domain events** fire AFTER `DB::transaction` commit (`DB::afterCommit()` or queued listeners). Never inside a transaction.
- **Idempotency-Key header + `idempotency_keys` table** on all state-changing endpoints (booking submit, payment initiate, refund, withdrawal, booking modification confirm). 24h TTL.
- **Standard `ApiResponse` envelope** on every API response: `{ data, meta, errors }`.
- **UTC server timezone always.** Convert to user timezone at the API Resource layer, never in business logic.

---

## 7. API Documentation Rules

Every new HTTP route MUST satisfy ALL four:

1. **`@bodyParam` PHPDoc on every Form Request field** — Scribe-compatible. Include type, required/optional, example, description. Bilingual fields require example for both locales.

   ```php
   /**
    * @bodyParam name object required Translatable name. Example: {"en": "Bouncy castle", "ar": "قلعة قافزة"}
    * @bodyParam name.en string required English name. Example: Bouncy castle
    * @bodyParam name.ar string required Arabic name. Example: قلعة قافزة
    * @bodyParam price_minor integer required Price in piastres. Example: 50000
    */
   ```

2. **`@response` PHPDoc with realistic EN+AR example data on every API Resource** — show the full envelope, both locales for translatable fields, a realistic ULID for `public_id`, integer minor units for money.

   ```php
   /**
    * @response 200 {
    *   "data": {
    *     "public_id": "01J9X7K3M2WZE0X8H4Q9N5T7V2",
    *     "name": {"en": "Bouncy castle", "ar": "قلعة قافزة"},
    *     "price_minor": 50000,
    *     "price_currency": "EGP"
    *   },
    *   "meta": {"locale": "en", "direction": "ltr"},
    *   "errors": []
    * }
    */
   ```

3. **Add the endpoint to `.specify/memory/api-registry.md`** in the same commit. One row, all columns populated (Method, Endpoint, Module, Phase, Auth, Roles, Request Body, Response, Documented). Endpoints not in the registry do not exist as far as the mobile/web teams are concerned.

4. **Bruno collection entry in `docs/api/collections/`** — one `.bru` file per endpoint (or grouped per resource), with the request body example, headers (including `Accept-Language` and `Idempotency-Key` where relevant), and at least one assertion. If the directory doesn't exist yet, create it; commit the `bruno.json` collection root with the first endpoint.

A merged endpoint that is missing any of these four artifacts is incomplete and must not be marked done in `tasks.md`.

---

## 8. Commit Rules

- **Format:** `<type>(<module>): <description>`
  - `<type>` ∈ `feat`, `fix`, `chore`, `docs`, `test`, `refactor`, `perf`, `style`, `ci`, `build`
  - `<module>` is the owning module (`identity`, `catalog`, `booking`, `payments`, `discovery`, `geography`, etc.) or `meta` for cross-cutting changes
  - Description: imperative mood, lowercase, no trailing period
  - Example: `feat(booking): add negotiation submit endpoint with idempotency`
- **Commit after each layer.** Don't bundle migration + model + action + controller + tests + Filament into one giant commit. Layer-per-commit lets review and rollback work cleanly:
  1. Migrations + factories
  2. Models + casts + scopes
  3. Form Requests + DTOs
  4. Actions (per type if type-aware)
  5. Controllers + API Resources + routes
  6. Filament Resources
  7. Listeners + domain events wiring
  8. Pest tests (unit + feature, per type when applicable)
  9. Documentation (api-registry, Bruno, README touch-ups)
- **Conventional commit body** explains the why; the title explains the what.
- **Co-Authored-By trailer** when the commit was produced by an agent.
- **Never `git push`.** Pushing is Ibrahim's call.
- **Never `--amend` a published commit.** Always create a new commit.
- **Never use `--no-verify`** to bypass hooks. Fix the underlying issue.

---

## 9. Hard Rules (non-negotiable, no exceptions)

1. **Do NOT `git push`** — to any remote, ever, unless Ibrahim says so explicitly in chat.
2. **Do NOT install packages not in `docs/specs/10_Package_List.md`.** If you think one is needed, stop and ask: "Is this package worth adding?" — justify, then update `10_Package_List.md` in the same commit before running `composer require`.
3. **Do NOT implement Phase 2 features.** If the request matches §4 above, refuse and reference the PRD section.
4. **Always read `tasks.md` before starting** any feature work. The active feature's `tasks.md` lives in `specs/NNN-feature-name/tasks.md`. If it doesn't exist, run `/speckit.tasks` first — don't guess.
5. **Always update `.specify/memory/api-registry.md` after adding endpoints** — same commit as the route definition. The registry is the canonical contract for the mobile and web teams.
6. **Always mark completed tasks as `[x]` in `tasks.md`** the moment they're done — not at end of session, not in batches. A task without `[x]` is presumed unfinished.
7. **Run `php artisan pint` and `./vendor/bin/phpstan analyse` before every commit.** All green required.
8. **Run `./vendor/bin/pest --bail` before every commit** that touches code under `app/`. Tests for type-aware features must include all three product type cases.
9. **Never put business logic in Models.** If you find yourself adding a method to a model that's not a relationship/cast/scope, stop and move it to an Action.
10. **Never use `if/elseif` on `product_type` strings.** Use `match($enum)` with the canonical `ProductType` enum.
11. **Never use float/decimal for money.** Use `Brick\Money\Money` and integer minor units.
12. **Never expose internal `id`** in API URLs. Use `public_id` (ULID, CHAR(26)).
13. **Never fire domain events inside `DB::transaction`.** Always after commit (`DB::afterCommit` or queued listeners).
14. **Never silently disagree with a spec.** Name the conflict and the file before proposing a deviation.

---

**Version:** 1.0.0 | **Ratified:** 2026-05-01 | **Locked-equivalent of:** `CLAUDE.md` + `.specify/memory/constitution.md`
