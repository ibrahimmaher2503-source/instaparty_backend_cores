# InstaParty — Project Memory (Constitution)

> Loaded by Claude Code at every session start. Treat as the project's constitution.
> If anything in this file conflicts with a chat instruction, **this file wins** unless explicitly overridden by Ibrahim in chat.

---

## Source-of-Truth Hierarchy

When specs disagree, resolve in this order (top wins):

1. `docs/specs/01_PRD.md`
2. **`CLAUDE.md`** (this file)
3. `docs/specs/03_Three_Product_Types.md`
4. `docs/specs/02_Tech_Decisions.md`
5. `docs/specs/05_Software_Description.md`
6. Conversation

`docs/specs/04_Bilingual_Spec.md`, `06_Customer_Journey.md`, `07_Vendor_Journey.md`, `08_Admin_Journey.md` are reference material — read when relevant.

---

## Stack & Architecture (LOCKED — Tech Decisions §1)

- **Backend:** Laravel 12 (PHP 8.3+), modular monolith in `app/Modules/{Name}/`
- **Admin:** Filament v3 at `/admin`
- **DB:** MySQL 8 / MariaDB 11, charset `utf8mb4`, collation `utf8mb4_unicode_ci`
- **Queue/Cache:** Redis
- **Auth:** Laravel Sanctum — SPA cookies (Next.js), tokens (Flutter)
- **Roles:** `spatie/laravel-permission` with per-product-type vendor scopes
- **Search:** Meilisearch via Laravel Scout
- **Storage:** DigitalOcean Spaces (prod) / MinIO (dev) via `spatie/laravel-medialibrary`
- **Real-time:** Laravel Reverb (business events) + Firebase (chat, push)
- **Payments:** Paymob via `PaymentGateway` interface (multi-gateway ready)
- **Money:** `Brick\Money`, integer minor units (piastres) — **NEVER floats**
- **i18n:** `spatie/laravel-translatable`, JSON columns, **EN+AR required everywhere**

---

## The Three Product Types (Central Domain)

**Every type-aware feature MUST cover all three.**

| Type | Code | Examples |
|---|---|---|
| Rental | `rental` | inflatables, mascots, photo booths |
| Sale | `sale` | cakes, food, gifts |
| Digital | `digital` | e-invitations, gift links, photo apps |

**Storage:** polymorphic base + type-specific detail (1:1)

- Base: `services` (with `product_type` discriminator ENUM)
- Details: `service_rental_details`, `service_sale_details`, `service_digital_details`
- ❌ Single-table inheritance is **forbidden**
- ❌ Three independent top-level tables are **forbidden**

**Code:** per-type classes + `match($enum)` for cross-type code

- `CreateRentalServiceAction`, `CreateSaleServiceAction`, `CreateDigitalServiceAction`
- `RentalSlotResolver`, `SaleSlotResolver`, `DigitalSlotResolver`
- `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` (Filament + API)
- ❌ `if/elseif` chains on type strings are **forbidden**
- Use `App\Modules\Catalog\Domain\Enums\ProductType`

Full reference: `docs/specs/03_Three_Product_Types.md`.

---

## Module Layout (per Tech Decisions §1)

```
app/Modules/{Name}/
├── Domain/
│   ├── Models/         # Relationships, casts, scopes ONLY — no business logic
│   ├── Enums/
│   ├── Events/
│   ├── States/         # spatie/laravel-model-states
│   └── Contracts/
├── Application/
│   ├── Actions/        # ONE execute() method per Action, fat
│   ├── Services/
│   ├── DTOs/
│   └── Listeners/
├── Infrastructure/
│   ├── Repositories/
│   └── Gateways/
├── Http/
│   ├── Controllers/    # 3-line action body MAX
│   ├── Requests/       # Per-type FormRequests
│   ├── Resources/      # API Resources (locale conversion happens here)
│   └── Middleware/
├── Filament/
│   └── Resources/      # Auto-discovered from this folder
├── Routes/
│   ├── customer.php
│   ├── vendor.php
│   └── admin.php
├── Database/
│   └── Migrations/     # Per-module, NOT in root database/migrations/
├── Resources/
│   └── lang/{en,ar}/
└── Providers/
    └── {Name}ServiceProvider.php
```

Phase 1 modules: **Identity, Catalog, Discovery, Booking, Negotiation, Payments, Settlement, Reviews, Communication, Reporting, Shared.**

---

## Coding Conventions (immutable rules)

1. **Thin controllers.** Action body max 3 lines. Real work goes into Action classes.
2. **Fat single-purpose Actions.** One public method: `execute()`. Constructor injection. Wrap mutations in `DB::transaction`.
3. **Models** in `Domain/Models` hold ONLY relationships, casts, scopes. **Never** business logic.
4. **Translatable fields** = JSON columns via `spatie/laravel-translatable` (e.g., `protected $translatable = ['name', 'description'];`).
5. **IDs:** internal `id` BIGINT, external `public_id` ULID (CHAR(26)), exposed in all API URLs.
6. **Money columns:** `BIGINT UNSIGNED` named `{field}_minor` + `{field}_currency CHAR(3)`. Cast via custom `MoneyCast` returning `Brick\Money\Money`.
7. **Domain events** fire **after** `DB::transaction` commit only. Use `DB::afterCommit()` or queued listeners. Never inside the transaction.
8. **Cross-type code** uses PHP `match($enum)`, never if/elseif on type strings.
9. **UTC server timezone** always. Convert to user timezone at the **API Resource layer**, never in business logic.
10. **Audit log** on every state transition and admin action via `audit_logs` (append-only).
11. **Idempotency-Key** header + `idempotency_keys` table on payment-mutating endpoints. 24h TTL.
12. **Standard `ApiResponse` envelope** on every API response: `{ data, meta, errors }`.
13. **Foreign keys** always declared. `ON DELETE CASCADE` only when domain-correct; default to `restrictOnDelete()`.
14. **Soft deletes** ONLY on tables listed in Tech Decisions §4 (users, vendor_profiles, services, bookings, reviews, categories, customer_addresses).
15. **Append-only** (no soft delete, no UPDATE except status fields): `wallet_ledger`, `audit_logs`, `payments`, `commissions`, `withdrawals` (status only), `booking_state_transitions`, `event_outbox`, `analytics_events`, `loyalty_ledger`.

---

## Testing Conventions (Pest)

**Test file structure:**
```
tests/
├── Feature/
│   └── Modules/
│       ├── Catalog/
│       ├── Booking/
│       └── ...
└── Unit/
    └── Modules/
```

**Required coverage** for every feature:
- Happy path
- Auth (unauthenticated → 401)
- Authorization (wrong role/vendor → 403)
- Validation (each required field, each rule)
- Idempotency (where applicable)
- Locale (EN response, AR response)
- **All three product types** (when feature is type-aware) — non-negotiable per Tech Decisions §2.4

**Pest patterns to prefer:**

```php
it('creates a rental service', function () {
    // ...
})->group('catalog', 'rental');

it('creates a sale service', function () {
    // ...
})->group('catalog', 'sale');

it('creates a digital service', function () {
    // ...
})->group('catalog', 'digital');
```

---

## Phase 1 Scope (DO NOT BUILD Phase 2)

**Out of scope** unless explicitly approved:

- Vendor subscription tiers (silver/gold/bronze)
- Platform-owned package products with separate accounting
- Dispute resolution engine
- Per-category card layout templates
- Vendor page slider
- Vendor QR / barcode catalog
- Advanced tax invoicing beyond foundational readiness

If asked to build any of these, **push back** and reference this file + PRD §5.2 / §11.

---

## Build & Run Commands

```bash
# Setup
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

# Development
php artisan serve
php artisan queue:work
php artisan reverb:start
php artisan schedule:work

# Testing
./vendor/bin/pest
./vendor/bin/pest --filter=BookingTest
./vendor/bin/pest --group=rental
./vendor/bin/pest --parallel

# Code quality
./vendor/bin/pint
./vendor/bin/phpstan analyse

# Filament
php artisan shield:generate --all       # After every new Filament resource
php artisan filament:cache-components

# Search
php artisan scout:flush "App\Modules\Catalog\Domain\Models\Service"
php artisan scout:import "App\Modules\Catalog\Domain\Models\Service"
```

---

## Developer Context

- **Developer:** Ibrahim, full-stack, based in Benha, Egypt
- **Working language:** English + Egyptian Arabic mix
- **Phase:** Phase 1 backend ONLY (Laravel API + Filament admin)
- **Mobile (Flutter) and web (Next.js) come AFTER backend is done**
- **Preference:** copy-paste-ready inline code/markdown; files only when explicitly asked

---

## Spec-Kit Workflow (when used)

If spec-kit is installed (`.specify/` exists), use it for **per-module feature work**:

1. `/speckit.constitution` is satisfied by this CLAUDE.md + `docs/specs/` — don't regenerate
2. For each module: `/speckit.specify` → `/speckit.plan` → `/speckit.tasks` → `/speckit.implement`
3. Module specs live in `specs/NNN-module-name/`
4. **Plan must reference `docs/specs/02_Tech_Decisions.md` and `docs/specs/03_Three_Product_Types.md`** before generating tasks

---

## When Generating Migrations — Always

- `declare(strict_types=1);` at top
- Anonymous class migration (Laravel 12 default)
- Set `$table->charset = 'utf8mb4'` and `$table->collation = 'utf8mb4_unicode_ci'`
- `$table->bigIncrements('id')`
- `$table->char('public_id', 26)->unique()` (ULID)
- Money: `$table->unsignedBigInteger('{field}_minor')` + `$table->char('{field}_currency', 3)`
- Translatable: `$table->json('name')` then `protected $translatable = ['name'];` on the model
- Foreign keys: `$table->foreignId('xxx_id')->constrained()->restrictOnDelete()` unless cascade is correct
- Indexes for FK + status combos always declared

Detailed rules: `.claude/rules/migrations.md`.

---

## When Generating Filament Resources — Always

- Per product type: separate Resources (`RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource`)
- Group under "Services" navigation
- Use `filament/spatie-laravel-translatable-plugin` for all translatable fields with EN/AR tabs
- Use `filament-shield` for permissions
- Run `php artisan shield:generate --all` after creating new resources

Detailed rules: `.claude/rules/filament.md`.

---

## Always Read These Specs Before Architectural Work

- Schema/DB questions → `docs/specs/02_Tech_Decisions.md` §4
- Product-type questions → `docs/specs/03_Three_Product_Types.md`
- Bilingual/i18n questions → `docs/specs/04_Bilingual_Spec.md`
- Booking flow questions → `docs/specs/01_PRD.md` §6 + §7
- Customer/Vendor/Admin journey → `docs/specs/06_*.md`, `07_*.md`, `08_*.md`

---

## Pushback Triggers

Push back honestly when:

- I propose something out of Phase 1 scope (reference §5.2 of PRD)
- I propose something contradicting Tech Decisions
- I propose generic "service" code that ignores the three product types
- I propose floats for money
- I propose business logic in Models
- I propose if/elseif chains on type strings
- I propose Phase 2 features without scoping them as such

Don't be silent — name the conflict and reference the spec.
