# InstaParty — Project Index

> Compact one-line index of every spec, ADR, current phase, tech stack, and locked package list. Read this file at the start of any spec-kit command to orient quickly without loading every full document.
>
> When this index disagrees with the source documents, the source documents win. Refresh this index when adding new specs, ADRs, or phases.

---

## docs/specs/

| File | One-line summary |
|---|---|
| `docs/specs/01_PRD.md` | Phase 1 PRD — FR-1…FR-30, BR-1…BR-6, agreed client revisions, §5.2 + §11 list explicitly out-of-scope Phase 2 features |
| `docs/specs/02_Tech_Decisions.md` | Locked tech stack — Laravel 12 + Filament v3 + MySQL 8 + Redis + Meilisearch + Paymob, modular monolith, three-product-type code rules |
| `docs/specs/03_Three_Product_Types.md` | Central domain reference for rental / sale / digital — polymorphic base + 3 detail tables, per-type lifecycles, refund policies, permissions |
| `docs/specs/04_Bilingual_Spec.md` | Full EN/AR/RTL spec — JSON translatable columns, both locales required everywhere, RTL Filament, locale conversion at API Resource layer |
| `docs/specs/05_Software_Description.md` | Original client brief (Arabic + English) — vision, target audience, three product types in business language |
| `docs/specs/06_Customer_Journey.md` | Customer flow with Mermaid — register → discover → wishlist → draft → negotiate → pay → fulfill → review |
| `docs/specs/07_Vendor_Journey.md` | Vendor flow with Mermaid — register → submit docs → approval per type → publish services → quote → fulfill → settle |
| `docs/specs/08_Admin_Journey.md` | Admin flow with Mermaid — vendor approvals, moderation queues, dispute mediation, reports, CMS, settings |
| `docs/specs/09_Phasing_Plan.md` | 26 micro-phases (≤3 days each) over 8 weeks, with per-phase cut-lists, exit criteria, and ADR mandates |
| `docs/specs/10_Package_List.md` | Locked Composer + NPM packages with rationale, version constraints, and explicitly rejected alternatives |
| `docs/specs/11_DB_Schema.md` | Locked 60-table / 13-module DB schema — append-only ledgers, soft-delete tables, money/translatable conventions, critical indexes |

---

## docs/adr/

| File | Status | One-line summary |
|---|---|---|
| `docs/adr/0001-modular-monolith-pattern.md` | Accepted (2026-04-15) | Modular monolith over microservices for Phase 1 — clean module boundaries enable surgical Phase 2 extraction |
| `docs/adr/0003-identity-module.md` | Accepted (2026-04-26) | Identity module — vendors, customers, two-step approval gate, per-type approvals, document storage, 2FA |
| `docs/adr/0004-catalog-module.md` | Accepted (2026-04-29) | Catalog module — polymorphic services + 3 detail tables, per-type Resources/Actions, inventory reservations |
| `docs/adr/ADR-001-geography-module.md` | Accepted | Geography as first-class module — owns governorates / regions / cities, exposed via `GeographyRepository` contract |
| `docs/adr/README.md` | — | ADR log index — naming convention (`NNNN-slug.md`), template location, status lifecycle |

---

## Current Phase

**Active:** Phase 3.2 — Booking: Negotiation Loop (just completed — commit `3447460`).
**Next up:** Phase 4.0 — Payments: Paymob Gateway (2 days, Week 5).

The 26-phase index lives in `docs/specs/09_Phasing_Plan.md`; phases run from `0.0` (Foundation Setup) through `7.2` (Documentation + Retrospective). Active feature branch is `005-booking-draft-items`; spec-kit feature folder is `specs/006-booking-negotiation-loop/`.

---

## Tech Stack Summary (from `02_Tech_Decisions.md`)

| Layer | Choice |
|---|---|
| Framework | Laravel 12 (PHP 8.3+) |
| Pattern | Modular monolith — `app/Modules/{Name}/` with Domain / Application / Infrastructure / Http / Filament layers |
| Admin UI | Filament v3 at `/admin` (auto-discovered per module) |
| Database | MySQL 8 / MariaDB 11, `utf8mb4` / `utf8mb4_unicode_ci` |
| Cache + Queue | Redis (predis) |
| Auth | Laravel Sanctum — SPA cookies (Next.js), tokens (Flutter) |
| Authorization | `spatie/laravel-permission` with per-product-type vendor scopes |
| Search | Meilisearch via Laravel Scout |
| Storage | DigitalOcean Spaces (prod) / MinIO (dev) via S3 driver — typed docs direct, galleries via spatie/medialibrary |
| Realtime | Laravel Reverb (business events) + Firebase (chat, push) |
| Payments | Paymob via `PaymentGateway` interface (multi-gateway ready, Phase 2 = Tabby/Tamara) |
| Money | `brick/money`, integer minor units (`{field}_minor` BIGINT + `{field}_currency` CHAR(3)) — no floats |
| i18n | `spatie/laravel-translatable` JSON columns, EN+AR mandatory everywhere |
| State machines | `spatie/laravel-model-states` |
| Activity / audit | `spatie/laravel-activitylog` + custom `audit_logs` table |
| Excel | `maatwebsite/excel` |

**Inviolable rules:** modules communicate only via domain events or `Domain/Contracts/` interfaces; never import another module's Eloquent Model directly. Type-aware code uses `match($enum)`, never `if/elseif` on type strings. Domain events fire `DB::afterCommit`. UTC server timezone, locale conversion at the API Resource layer.

---

## Package List Summary (from `10_Package_List.md`)

**Foundation:** `laravel/framework:^12.0`, `laravel/sanctum:^4.0`, `laravel/scout:^10.10`, `laravel/reverb:^1.0`, `predis/predis:^2.2`.

**Identity & auth:** `spatie/laravel-permission:^6.10`, `pragmarx/google2fa:^8.0`, `bacon/bacon-qr-code:^3.0`.

**Domain (catalog / money / states / media):** `spatie/laravel-translatable`, `spatie/laravel-medialibrary`, `spatie/laravel-model-states`, `spatie/laravel-activitylog`, `brick/money`, `maatwebsite/excel`, `meilisearch/meilisearch-php`.

**Filament v3 + curated plugins ONLY:** `filament/filament:^3.x`, `filament/spatie-laravel-translatable-plugin`, `filament/spatie-laravel-media-library-plugin`, `bezhansalleh/filament-shield`, `bezhansalleh/filament-language-switch`, `awcodes/filament-tiptap-editor`, `pxlrbt/filament-excel`, `saade/filament-fullcalendar`. **No other Filament plugins** without amending `10_Package_List.md`.

**Dev / quality:** `pestphp/pest`, `pestphp/pest-plugin-laravel`, `larastan/larastan`, `laravel/pint`, `barryvdh/laravel-debugbar` (dev-only).

**Locked rule:** No new package without an entry in `10_Package_List.md` in the same commit. When tempted to install something else, push back, justify in one paragraph, then update the list.

---

## Refresh checklist

When you change one of the following, refresh the matching section above:

- New / removed `docs/specs/*.md` → refresh §`docs/specs/`.
- New / superseded ADR in `docs/adr/` → refresh §`docs/adr/`.
- Phase complete or re-planned in `09_Phasing_Plan.md` → refresh §Current Phase.
- Tech swap in `02_Tech_Decisions.md` → refresh §Tech Stack Summary.
- Package added/removed in `10_Package_List.md` → refresh §Package List Summary.
