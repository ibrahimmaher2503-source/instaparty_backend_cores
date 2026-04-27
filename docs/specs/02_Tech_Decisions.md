# InstaParty — Locked Tech Decisions (Phase 1)

This document is the single source of truth for architectural and stack decisions on the InstaParty platform. Any change requires explicit conversation and an update of this file before implementation.

When this document conflicts with anything else (chat, older notes, training data), this document wins — except the PRD, CLAUDE.md, and `09_Three_Product_Types.md`, which take precedence in that order.

---

## 1. Architecture

| Decision | Choice |
|---|---|
| Pattern | Modular Monolith |
| Module location | `app/Modules/{ModuleName}/` |
| Module isolation | Domain events, repository interfaces, no cross-module model imports |
| Modules in Phase 1 | Identity, Catalog, Discovery, Booking, Negotiation, Payments, Settlement, Reviews, Communication, Reporting, Shared |
| Inter-module communication | Domain events via Laravel event system |
| Events fire | After `DB::transaction` commit only (`DB::afterCommit` or queued listeners) |
| Layer organization (per module) | Domain → Application → Infrastructure → Http → Filament |
| Module routes | One file per role per module: `Routes/customer.php`, `Routes/vendor.php`, `Routes/admin.php` |
| Module migrations | Live in `Modules/{Name}/Database/Migrations/` (not in root `database/migrations/`) |
| Module Filament resources | Auto-discovered from `Modules/{Name}/Filament/Resources/` |
| Module service provider | Registers routes, migrations, translations, listeners, Filament resources, policies |

---

## 2. Product Types — Central Domain Architecture

This section is the locked reference for how the three product types are modeled and implemented. For full per-type schemas, lifecycles, and behaviors, see `09_Three_Product_Types.md`.

### 2.1 The Three Types

| Type | Code | Examples |
|---|---|---|
| Rental | `rental` | Inflatables, mascots, photo booths, decorations, costumes |
| Sale | `sale` | Cakes, food, snacks, gifts, party supplies |
| Digital | `digital` | E-invitations, photo apps, gift links, gift cards |

### 2.2 Storage Architecture

| Decision | Choice |
|---|---|
| Storage pattern | Polymorphic base + type-specific detail (1:1) |
| Base table | `services` |
| Detail tables | `service_rental_details`, `service_sale_details`, `service_digital_details` |
| Discriminator column | `services.product_type` (ENUM) |
| Relationship | 1:1 from `services` → corresponding detail table via `service_id` PK |
| Single Table Inheritance | **Forbidden** (no mega-table with nullable columns) |
| Three separate top-level tables (no shared base) | **Forbidden** |
| Booking item polymorphism | `booking_items` carries `product_type` (denormalized), `type_snapshot` JSON (immutable rules at booking time), `fulfillment_data` JSON (runtime state) |

### 2.3 Code Architecture

| Decision | Choice |
|---|---|
| Canonical enum | `App\Modules\Catalog\Domain\Enums\ProductType` (cases: `Rental`, `Sale`, `Digital`) |
| Per-type Form Requests | `Create{Type}ServiceRequest`, `Update{Type}ServiceRequest` |
| Per-type Actions (writes) | `Create{Type}ServiceAction`, `Update{Type}ServiceAction`, `Import{Type}ServicesFromExcelAction` |
| Cross-type Actions | `PublishServiceAction`, `ArchiveServiceAction`, `ModerateServiceAction` |
| Per-type API endpoints (writes) | `POST/PATCH/DELETE /api/v1/vendor/services/{type}` |
| Unified API endpoints (reads) | `GET /api/v1/vendor/services?type={...}`, `GET /api/v1/customer/services?type={...}` |
| Per-type API Resources | `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` (extending base `ServiceResource`) |
| Per-type Excel templates | Three separate templates with paired `_en` / `_ar` bilingual columns |
| Per-type Filament resources | `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` (grouped under "Services" navigation) |
| Per-type slot resolvers | `RentalSlotResolver`, `SaleSlotResolver`, `DigitalSlotResolver` (strategy pattern) |
| Per-type price calculators | `RentalPriceCalculator`, `SalePriceCalculator`, `DigitalPriceCalculator` |
| Per-type fulfillment state machines | Three distinct state machines via spatie/laravel-model-states |
| Per-type refund policies | Computed by `RefundPolicyService` using `match($productType)` |
| Cross-type code style | Use PHP `match($enum)` — never if/elseif chains on type strings |

### 2.4 Operational Per-Type Decisions

| Decision | Choice |
|---|---|
| Vendor approval per type | Yes — vendor approved per type independently via `vendor_approved_product_types` table |
| Vendor permissions per type | `service.create.rental.own`, `service.create.sale.own`, `service.create.digital.own` |
| Commission rates per type | Configurable per `(category × product_type)` in `commission_rates` table |
| Search facets per type | `product_type` is filterable facet in Meilisearch; type-specific facets exposed (e.g., `requires_electricity` for rental) |
| Notification templates per type | Type-specific events (rental delivery, sale delivery, digital redemption) with bilingual templates |
| Reporting breakdowns | Per-type breakdowns required in all relevant admin reports |
| Testing requirements | Every type-aware feature MUST cover all three types in tests |

---

## 3. Authentication

| Decision | Choice |
|---|---|
| Auth library | Laravel Sanctum |
| Web (Next.js) | SPA cookie mode |
| Mobile (Flutter) | Token mode (`Authorization: Bearer`) |
| User table | Single `users` table |
| Roles & permissions | spatie/laravel-permission |
| Multi-role support | Yes — user can hold multiple roles (e.g., customer + vendor) |
| Per-product-type vendor permissions | Yes — vendor permissions can be scoped per type |
| 2FA | Required for admin accounts |
| 2FA mechanism | TBD (TOTP via Google Authenticator vs SMS OTP) |
| Phone verification | Required for customers and vendors |
| Email verification | Required for vendors and admins; optional for customers |
| Password reset | Email-based with signed URL, 60-min expiry |
| Token abilities/scopes (mobile) | `customer`, `vendor` (one role per token) |
| Session lifetime (web) | 2 hours sliding |

### 3.1 Sanctum Configuration

| Setting | Value |
|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | List of Next.js domains per environment |
| `config/sanctum.php` `guard` | `['web']` |
| `config/cors.php` `supports_credentials` | `true` |
| `config/session.php` `domain` | `.instaparty.com` (with leading dot for subdomains) |
| `config/session.php` `same_site` | `lax` |
| `config/session.php` `secure` | `true` in production |
| `config/session.php` `http_only` | `true` |

---

## 4. Database

| Decision | Choice |
|---|---|
| Engine | MySQL 8 or MariaDB 11 |
| Charset | utf8mb4 |
| Collation | utf8mb4_unicode_ci |
| Internal IDs | BIGINT UNSIGNED auto-increment, column `id` |
| External IDs | CHAR(26) ULID, column `public_id`, exposed in all API URLs |
| Primary key on detail tables | `service_id` BIGINT UNSIGNED (1:1 with `services.id`) |
| Soft deletes | users, vendor profiles, services, bookings, reviews, categories |
| Append-only (no soft delete) | wallet_ledger, audit_logs, payments, commissions, withdrawals, booking_state_transitions |
| Charset enforcement | Set in every migration's `Schema::create` callback |
| Foreign keys | Always declared; `ON DELETE CASCADE` only where domain-correct |
| JSON columns | Used for translatable fields, `type_snapshot`, `fulfillment_data`, `customization_fields`, `size_dimensions` |
| Money columns | `BIGINT UNSIGNED` named `{field}_minor`, paired with `{field}_currency CHAR(3)` |
| Decimal columns | Allowed only for non-money values (weights, dimensions, ratings) |

### 4.1 Required Indexes

| Table | Index |
|---|---|
| `services` | `(vendor_id, status)`, `(category_id, product_type, status)`, `public_id` UNIQUE |
| `booking_items` | `(booking_vendor_id)`, `(service_id)`, `(product_type, item_status)` |
| `booking_vendors` | `(vendor_id, sub_status, response_deadline)` |
| `bookings` | `(customer_id, status, created_at)`, `public_id` UNIQUE |
| `wallet_ledger` | `(wallet_id, created_at)` |
| `payments` | `(gateway, gateway_ref)` UNIQUE (idempotency) |
| `audit_logs` | `(auditable_type, auditable_id, created_at)` |

---

## 5. Money & Currency

| Decision | Choice |
|---|---|
| Storage type | BIGINT UNSIGNED, column suffix `_minor` (piastres for EGP) |
| Currency column | CHAR(3), ISO 4217 (`EGP`, `SAR`, `AED`) |
| Phase 1 currency | EGP only |
| Multi-currency schema | Day 1 ready (every money column paired with currency) |
| In-PHP type | `Brick\Money\Money` value object |
| Eloquent cast | Custom `MoneyCast` reading `_minor` + `_currency` columns |
| Arithmetic | Money methods only (`plus`, `minus`, `multipliedBy`, `dividedBy`) |
| Float for money | **Forbidden** |
| Raw `+ - * /` operators on money | **Forbidden** |
| Commission rates | Integer basis points (10000 = 100%, 1500 = 15%) |
| Commission rate resolution | Most-specific match wins: `(category × type)` → `(category × NULL)` → `(NULL × type)` → `(NULL × NULL)` |
| Currency conversion | Out of Phase 1 scope; schema supports it |
| Display formatting | Locale-aware via `Money::formatTo($locale)` |
| Tax handling | Foundational only in Phase 1; advanced tax invoicing is Phase 2 |

---

## 6. Time & Locale

| Decision | Choice |
|---|---|
| Server timezone | UTC always (`config/app.php`) |
| Storage timezone | UTC always (all `TIMESTAMP` columns) |
| User timezone | Stored on `users.timezone` |
| Default user timezone | `Africa/Cairo` |
| Conversion location | API Resource layer only (never in business logic) |
| Default app locale | `ar` (Arabic) |
| Supported locales | `ar`, `en` |
| Locale resolution order | `?lang=` query → `Accept-Language` header → user `preferred_locale` → app default |
| Clock injection | `Clock` interface injected for testability; never `Carbon::now()` directly in business logic |
| Hijri calendar | Out of scope Phase 1 |
| Numeral system | Western Arabic (0-9) by default; Eastern (٠-٩) optional user preference |

---

## 7. Translatable Content

| Decision | Choice |
|---|---|
| Library | spatie/laravel-translatable |
| Storage | JSON columns |
| Required locales for entities | Both `en` and `ar` (enforced in Form Requests) |
| API single-locale response | Default, based on `Accept-Language` header |
| API multi-locale response | `?translations=all` query param (admin and vendor edit screens) |
| UI strings location | `lang/{locale}/*.php` per module, loaded via `loadTranslationsFrom` |
| Translatable entities | occasions, categories, services (all 3 types), service themes, vendor display names, vendor bios, cities, regions, governorates, notification templates, email templates, SMS/WhatsApp/push templates, CMS pages, loyalty rules |
| Excel bilingual columns | Paired `_en` / `_ar` columns per translatable field, **across all three product type templates** |
| RTL/LTR | Full support across web, mobile, admin |
| Mixed-script handling (chat) | Bubble direction follows message language, not UI language |

---

## 8. Storage

| Decision | Choice |
|---|---|
| Production | DigitalOcean Spaces (S3-compatible) |
| Region (production) | Frankfurt (`fra1`) |
| Local dev | MinIO in Docker |
| Public bucket | `instaparty-public` (CDN-fronted) |
| Private bucket | `instaparty-private` (signed URLs only) |
| Library | `spatie/laravel-medialibrary` — gallery-style use cases only (see strategy below) |
| Image conversions | thumb (200px), medium (600px), large (1200px), webp variants |
| Disk driver | `s3` with custom endpoint for Spaces |
| Use path style endpoint | `false` for Spaces, `true` for MinIO |
| Chat media | Firebase Storage (separate from app storage) |

### Per-asset storage strategy (LOCKED)

| Asset | Storage method | Bucket | Access |
|---|---|---|---|
| Service images (galleries) | `spatie/laravel-medialibrary` — `HasMedia`, `gallery` collection | `instaparty-public` | CDN public URL |
| Vendor profile avatar / cover | `spatie/laravel-medialibrary` — `HasMedia`, `avatar`/`cover` collection | `instaparty-public` | CDN public URL |
| Vendor documents (CR, tax card, national ID, IBAN proof) | Direct `Storage::disk('s3-private')->putFileAs(...)` — `file_path`/`file_name` on `vendor_documents` table | `instaparty-private` | Signed URL (15-min TTL) |
| Settlement proofs | Direct `Storage::disk('s3-private')->putFileAs(...)` — explicit columns on `withdrawals` table | `instaparty-private` | Signed URL (15-min TTL) |
| Chat media | Firebase Storage | Firebase project | Firebase download URL |

**Rationale for split strategy**: `spatie/laravel-medialibrary` excels at gallery-style use cases where image conversions, collections, and media ordering are needed. Vendor documents and settlement proofs are **entities with review state** — they have their own tables (`vendor_documents`, `withdrawals`) with `status`, `reviewed_by`, `reviewed_at`, `review_notes` columns. Coupling these to MediaLibrary's morph relation adds complexity without benefit. Direct S3 with explicit `file_path`/`file_name` columns keeps the review workflow in one table and avoids joining to the `media` table for every query.

---

## 9. Search

| Decision | Choice |
|---|---|
| Engine | Meilisearch |
| Library | Laravel Scout |
| Driver | `meilisearch` |
| Indexed entities | services (with `product_type` facet), vendors, categories |
| Languages | Arabic + English with locale-specific tokenizers |
| Sync | Queued via Scout (`SCOUT_QUEUE=true`) |
| Filterable attributes (services) | `product_type`, `category_id`, `category_path`, `occasion_ids`, `vendor_id`, `is_active`, `price_minor`, `currency`, plus type-specific facets |
| Type-specific facets | Rental: `requires_electricity`, `requires_outdoor_space`; Sale: `is_perishable`, `allows_customization`; Digital: `delivery_method`, `has_expiry` |
| Searchable attributes | `name_en`, `name_ar`, `short_description_en`, `short_description_ar` |
| Sortable attributes | `price_minor`, `vendor_rating`, `created_at` |
| Index reset | Run on deploy via Scout artisan commands |

---

## 10. Real-Time

| Decision | Choice |
|---|---|
| Business events (in-app) | Laravel Reverb (WebSocket) |
| Chat messaging | Firebase Cloud Firestore |
| Push notifications | Firebase Cloud Messaging |
| Chat audit | Firestore listener service writes message metadata to MySQL `chat_message_log` |
| Chat moderation | Phone/email regex blocker, runs as queue job before delivery |
| Chat message types | Text, image, voice note |
| Chat retention | All messages retained for audit; redacted messages flagged in admin |
| Reverb channels | Per-user, per-vendor, per-admin private channels; presence channels for active monitoring |

---

## 11. Payments

| Decision | Choice |
|---|---|
| Primary gateway (Egypt) | Paymob |
| Architecture | `PaymentGateway` interface in `Modules/Payments/Domain/Contracts/`, swappable implementations in `Infrastructure/Gateways/` |
| GCC readiness | Adapters for Tabby, Tamara, HyperPay (not built Phase 1) |
| Idempotency | `Idempotency-Key` header + `idempotency_keys` table, 24-hour cache |
| Webhooks | Per-gateway URL (`/webhooks/paymob`), signature verification, dispatch domain event, return 200 immediately |
| Webhook processing | Heavy work in queued listeners |
| Refunds | Via gateway adapter, ledger entry written |
| Refund policies | **Per product type** via `RefundPolicyService` |
| Rental refund window | Up to 24h before `event_start` (configurable per service) |
| Sale refund window | Until item enters `InPreparation` state (state-driven, not time-driven) |
| Digital refund policy | Per `is_refundable_after_delivery` flag on service detail |
| Partial refunds | Supported via gateway adapter |
| Payment methods | Card, wallet, instalment partners (gateway-dependent) |
| Split payments | Supported (multiple payments against one booking) |

---

## 12. Excel Imports

| Decision | Choice |
|---|---|
| Library | Maatwebsite/Laravel-Excel |
| Number of templates | **Three: rental, sale, digital** (one per product type) |
| Bilingual | All translatable fields have `_en` / `_ar` paired columns |
| Validation | Per-row, validation-driven |
| Partial commits | **Forbidden** — all rows valid or none committed |
| Error reporting | Per-row error feedback with line number, in user's locale |
| Queue | Background processing for files > 50 rows |
| Storage | Uploaded files retained 30 days for audit |
| Image references | Filename references in Excel, paired with separate folder upload |
| Conditional validation | Type-specific (e.g., `lead_time_hours` required when `is_made_to_order = true` for sale) |
| Template download | Vendors download bilingual template per type from app/web before upload |

For full template column lists per type, see `09_Three_Product_Types.md` §10.

---

## 13. Admin Panel

| Decision | Choice |
|---|---|
| Library | Filament v3 |
| Path | `/admin` |
| Auth guard | `web` (session-based) |
| Permissions plugin | bezhansalleh/filament-shield |
| Translatable plugin | filament/spatie-laravel-translatable-plugin |
| Media plugin | filament/spatie-laravel-media-library-plugin |
| Tags plugin | filament/spatie-laravel-tags-plugin (if used) |
| Resource discovery | Custom: scans `app/Modules/*/Filament/Resources/` |
| Service resources | **Three: `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource`** grouped under "Services" navigation |
| Cross-type service view | Optional unified "All Services" page for admin-wide monitoring |
| Locales in panel | Arabic + English with language tabs |
| RTL support | Native via panel locale switcher + custom CSS |
| Default locale | Admin user's `preferred_locale` |
| Permission generation | `php artisan shield:generate --all` after every new resource |

### 13.1 Filament Resources Required (Phase 1)

- Settings (occasions, categories, regions, cities, governorates)
- Users
- Vendor Profiles + Vendor Approval Queue (with per-type approval)
- Service Categories & Field Schemas (per category × type)
- **Rental Services**
- **Sale Services**
- **Digital Services**
- Bookings Monitor (with per-type filter)
- Booking Item Fulfillment Monitor (per-type lifecycles)
- Withdrawals Queue
- Wallets & Ledger Viewer
- Commission Rules (per-(category × type) rates)
- Notification Templates (per-locale, per-event-type)
- Campaigns Builder (with optional type segmentation)
- Reviews Moderation
- Audit Log Viewer
- Reports & Dashboards (with per-type breakdowns)

---

## 14. Frontend (Next.js)

| Decision | Choice |
|---|---|
| Framework | Next.js (latest stable) |
| Auth | Sanctum SPA cookies (`withCredentials: true`) |
| CSRF | `GET /sanctum/csrf-cookie` before login |
| HTTP client | Axios with `withXSRFToken: true` (axios v1.7+) |
| i18n | next-intl |
| URL strategy | Locale prefix `/en/...` and `/ar/...` |
| Default locale | `ar` |
| RTL CSS | Tailwind RTL plugin or logical properties (`margin-inline-start`, etc.) |
| API client | Generated from OpenAPI spec via `openapi-typescript-codegen` |
| Type-aware UI | Different cards/layouts per product type in browse and booking summary |
| SEO | Per-locale meta, hreflang tags, per-locale sitemaps |
| Server-side rendering | Used for catalog browse and individual service pages |

---

## 15. Mobile (Flutter)

| Decision | Choice |
|---|---|
| Framework | Flutter (latest stable) |
| State management | GetX |
| Architecture | Clean Architecture, feature-first folder structure |
| i18n | `intl` package + ARB files (`app_en.arb`, `app_ar.arb`) |
| Auth | Sanctum tokens via `Authorization: Bearer` |
| Local storage | Hive or Isar (TBD by mobile team) |
| Firebase | Firestore (chat), FCM (push), Storage (chat media), Analytics, Crashlytics |
| Fonts (Arabic) | Cairo, Tajawal, or IBM Plex Sans Arabic |
| Fonts (English) | Inter or Poppins |
| Type-aware UI | Different card layouts per product type in catalog browse |
| RTL handling | `Directionality` widget at root, `EdgeInsetsDirectional` everywhere, no hardcoded `left`/`right` |
| Currency display | Locale-aware via `intl.NumberFormat.currency` |
| Date/time | Locale-aware via `intl.DateFormat.yMMMd` |

---

## 16. Deployment

| Decision | Choice |
|---|---|
| Hosting | VPS — Hetzner or DigitalOcean |
| Containerization | Docker Compose |
| Reverse proxy | Caddy (automatic HTTPS via Let's Encrypt) |
| Environments | Local, Staging, Production |
| CI/CD | GitHub Actions |
| Image registry | GitHub Container Registry (ghcr.io) |
| Backup tool | spatie/laravel-backup |
| Backup destination | Separate S3 bucket (different provider for redundancy) |
| Backup cadence | Daily DB dump, weekly full backup |
| Sizing (staging) | Hetzner CX22 — 2 vCPU, 4GB RAM, 40GB SSD |
| Sizing (production) | Hetzner CCX13 or DO 4GB — 2 vCPU, 8GB RAM, 80GB SSD |
| Multi-region | Out of scope Phase 1 |
| Auto-scaling | Out of scope Phase 1 (vertical scaling only) |
| Zero-downtime deploys | Yes via Caddy + rolling Docker replacement |
