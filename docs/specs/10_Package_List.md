# InstaParty — Package List (LOCKED for Phase 1)

> **Rule:** No new package without updating this file in the same commit. Anything not on this list requires Ibrahim's explicit approval before `composer require`.
>
> Version constraints: I pin to caret-major to allow patch updates. If a major upgrade is needed mid-Phase 1, that's a deliberate decision, not an accident.

---

## Table of contents

1. Composer — Foundation
2. Composer — Domain (per module)
3. Composer — Filament v3 + Plugins
4. Composer — Dev / Quality
5. NPM — Build Tooling
6. Order of installation
7. Why these, not those (alternatives I considered and rejected)

---

## 1. Composer — Foundation

```bash
composer require \
  laravel/framework:^12.0 \
  laravel/sanctum:^4.0 \
  laravel/scout:^10.10 \
  laravel/reverb:^1.0 \
  predis/predis:^2.2
```

| Package | Purpose | Notes |
|---|---|---|
| `laravel/framework` | Framework | Laravel 12 (PHP 8.3+) |
| `laravel/sanctum` | Auth (SPA cookies for Next.js, tokens for Flutter) | Configured per `02_Tech_Decisions.md` §3.1 |
| `laravel/scout` | Search abstraction | Used with Meilisearch driver |
| `laravel/reverb` | WebSocket server for business events | Per-user, per-vendor, per-admin private channels |
| `predis/predis` | Redis client | Sessions, cache, queues |

---

## 2. Composer — Domain

### Identity & Authorization

```bash
composer require \
  spatie/laravel-permission:^6.10 \
  pragmarx/google2fa:^8.0 \
  bacon/bacon-qr-code:^3.0
```

| Package | Purpose |
|---|---|
| `spatie/laravel-permission` | Roles + permissions, per-product-type scopes |
| `pragmarx/google2fa` | TOTP (admin 2FA per `02_Tech_Decisions.md` §3) |
| `bacon/bacon-qr-code` | QR code generation for 2FA setup |

### Catalog & Translatable Content

```bash
composer require \
  spatie/laravel-translatable:^6.8 \
  spatie/laravel-medialibrary:^11.9 \
  spatie/laravel-tags:^4.6
```

| Package | Purpose |
|---|---|
| `spatie/laravel-translatable` | JSON columns, EN+AR per `04_Bilingual_Spec.md` |
| `spatie/laravel-medialibrary` | Service images, vendor documents, settlement proofs |
| `spatie/laravel-tags` | Service themes, occasion tags |

### Search

```bash
composer require meilisearch/meilisearch-php:^1.10
```

| Package | Purpose |
|---|---|
| `meilisearch/meilisearch-php` | Meilisearch driver dependency for Scout |

### Money

```bash
composer require brick/money:^0.10
```

| Package | Purpose |
|---|---|
| `brick/money` | Money value object — integer minor units. **Never replace with raw decimals.** |

### Booking & State Machines

```bash
composer require \
  spatie/laravel-model-states:^2.7 \
  spatie/laravel-data:^4.13
```

| Package | Purpose |
|---|---|
| `spatie/laravel-model-states` | Per-type fulfillment state machines (3 distinct graphs) |
| `spatie/laravel-data` | Typed DTOs between layers (replaces hand-rolled DTOs) |

### Payments

```bash
composer require \
  guzzlehttp/guzzle:^7.9 \
  symfony/http-client:^7.1
```

| Package | Purpose |
|---|---|
| `guzzlehttp/guzzle` | HTTP client for Paymob, Mailchimp, etc. |
| `symfony/http-client` | Alternative HTTP client where guzzle has issues; used by Mailchimp SDK |

> **Paymob has no official PHP SDK.** We build the integration ourselves in `Modules/Payments/Infrastructure/Gateways/PaymobGateway.php`. This is fine — Paymob's API is straightforward.

### Storage

```bash
composer require \
  league/flysystem-aws-s3-v3:^3.29
```

| Package | Purpose |
|---|---|
| `league/flysystem-aws-s3-v3` | DigitalOcean Spaces (S3-compatible) + MinIO local |

### Real-time & Chat

```bash
composer require \
  kreait/laravel-firebase:^5.10 \
  pusher/pusher-php-server:^7.2
```

| Package | Purpose |
|---|---|
| `kreait/laravel-firebase` | Firebase Firestore (chat audit) + FCM (push notifications) |
| `pusher/pusher-php-server` | Reverb broadcasting (Reverb compat) |

### Notifications (multi-channel)

```bash
composer require \
  laravel-notification-channels/fcm:^4.5 \
  laravel/vonage-notification-channel:^3.3 \
  netflie/whatsapp-cloud-api:^1.5 \
  mailchimp/marketing:^3.0
```

| Package | Purpose |
|---|---|
| `laravel-notification-channels/fcm` | Push notifications via Firebase Cloud Messaging |
| `laravel/vonage-notification-channel` | SMS (alternative: country-specific gateway like Mobily/Mobinil) |
| `netflie/whatsapp-cloud-api` | WhatsApp Business Cloud API (template messages) |
| `mailchimp/marketing` | Email marketing (campaigns + transactional via Mailchimp) |

> **SMS provider note:** Vonage (Nexmo) works in Egypt but coverage isn't great. If you have an Egyptian SMS gateway, swap the channel adapter — the abstraction lets you do that without touching templates.

### Excel Imports

```bash
composer require maatwebsite/excel:^3.1
```

| Package | Purpose |
|---|---|
| `maatwebsite/excel` | Three Excel templates (rental, sale, digital), bilingual columns, no partial commits |

### Audit & Backup

```bash
composer require \
  spatie/laravel-activitylog:^4.9 \
  spatie/laravel-backup:^9.2
```

| Package | Purpose |
|---|---|
| `spatie/laravel-activitylog` | Audit log on every state transition + admin action |
| `spatie/laravel-backup` | Daily DB dumps, weekly full backups to separate S3 bucket |

### Identifier Generation

ULIDs are now native to Laravel — use `Str::ulid()` and `HasUlids` trait. **No package needed.**

---

## 3. Composer — Filament v3 + Curated Plugins

### Core

```bash
composer require filament/filament:^3.2
php artisan filament:install --panels
```

### Curated plugins (locked)

```bash
composer require \
  bezhansalleh/filament-shield:^3.3 \
  filament/spatie-laravel-translatable-plugin:^3.2 \
  filament/spatie-laravel-media-library-plugin:^3.2 \
  filament/spatie-laravel-tags-plugin:^3.2 \
  filament/spatie-laravel-settings-plugin:^3.2 \
  filament/spatie-laravel-activitylog-plugin:^3.2 \
  awcodes/filament-tiptap-editor:^3.4 \
  bezhansalleh/filament-language-switch:^3.1 \
  pxlrbt/filament-excel:^2.4 \
  saade/filament-fullcalendar:^3.2
```

| Plugin | Purpose | Why it's in Phase 1 |
|---|---|---|
| `bezhansalleh/filament-shield` | Permission generation per resource | Required for per-product-type vendor permissions |
| `filament/spatie-laravel-translatable-plugin` | EN/AR tabs in every translatable form | Bilingual is mandatory, not optional |
| `filament/spatie-laravel-media-library-plugin` | Image upload UI (services, documents, proofs) | Service galleries + document upload |
| `filament/spatie-laravel-tags-plugin` | Tag picker UI for themes/categories | Themes pivot Filament UI |
| `filament/spatie-laravel-settings-plugin` | Admin-managed settings page | Platform fee defaults, region defaults |
| `filament/spatie-laravel-activitylog-plugin` | Audit log viewer | Required by Tech Decisions auditability rule |
| `awcodes/filament-tiptap-editor` | Rich text for CMS pages (Terms, Privacy, About) | CMS pages need rich content, not plain markdown |
| `bezhansalleh/filament-language-switch` | Top-bar EN/AR switcher | Admin works in EN or AR per `04_Bilingual_Spec.md` §4.1 |
| `pxlrbt/filament-excel` | Per-resource Excel export | Reports + listings export |
| `saade/filament-fullcalendar` | Calendar views (booking timeline, vendor availability) | Booking Item Fulfillment Monitor |

### Plugins I considered and **rejected** for Phase 1

| Plugin | Why not now |
|---|---|
| `filament/notifications` (database) | Built-in Filament notifications are enough; database notifications add UI bloat |
| `awcodes/filament-curator` | Media library plugin is sufficient |
| `bezhansalleh/filament-google-analytics` | Reporting is internal-only in Phase 1 |
| `filament/forms` extra plugins | Avoid plugin sprawl; ship vanilla forms first |
| `filament-impersonate` | Useful but not Phase 1 critical |
| `filament-tabler-icons` / `filament-fontawesome` | Heroicons (Filament default) is enough |

If we hit a real need for any of these, add them with an entry in this file.

---

## 4. Composer — Dev / Quality

```bash
composer require --dev \
  pestphp/pest:^3.5 \
  pestphp/pest-plugin-laravel:^3.0 \
  pestphp/pest-plugin-arch:^3.0 \
  laravel/pint:^1.18 \
  larastan/larastan:^3.0 \
  nunomaduro/collision:^8.5 \
  fakerphp/faker:^1.23 \
  mockery/mockery:^1.6 \
  spatie/laravel-ignition:^2.8 \
  laravel/telescope:^5.2 \
  driftingly/rector-laravel:^2.0 \
  knuckleswtf/scribe:^5.9
```

| Package | Purpose |
|---|---|
| `pestphp/pest` | Test runner — locked per `CLAUDE.md` |
| `pestphp/pest-plugin-laravel` | Laravel-specific helpers in Pest |
| `pestphp/pest-plugin-arch` | Architecture tests (assert no model imports across modules, etc.) |
| `laravel/pint` | Code formatter — auto-runs via post-edit-pint hook |
| `larastan/larastan` | Static analysis level 8 |
| `nunomaduro/collision` | Better error rendering in console |
| `fakerphp/faker` | Factory data |
| `mockery/mockery` | Mocking in tests |
| `spatie/laravel-ignition` | Pretty error pages (dev only) |
| `laravel/telescope` | Local debugging — disabled in production |
| `driftingly/rector-laravel` | Automated upgrade rules (use sparingly) |
| `knuckleswtf/scribe` | Auto-generate API documentation from PHPDoc annotations (`@bodyParam`, `@response`); consumed by the Flutter mobile team |

---

## 5. NPM — Build Tooling

```bash
npm install --save-dev \
  vite \
  laravel-vite-plugin \
  tailwindcss \
  @tailwindcss/forms \
  @tailwindcss/typography \
  postcss \
  autoprefixer
```

That's the standard Filament-friendly Vite setup.

---

## 5b. NPM — Customer Frontend (`apps/frontend/`)

Co-located in this same git repo under `apps/frontend/`. The root Vite setup above stays untouched.

```bash
cd apps/frontend
npm install
```

### Production dependencies
| Package | Why |
|---|---|
| `next@15` | App Router + RSC (Tech Decisions §1) |
| `react@19`, `react-dom@19` | Pinned to Next 15's React requirement |
| `next-intl` | EN/AR locale routing with RTL `dir` attribute |
| `@tanstack/react-query` | Client-side cache for the cart/wizard surfaces |
| `zustand` | Cart + booking-wizard state |
| `react-hook-form` + `@hookform/resolvers` + `zod` | Forms that mirror Laravel FormRequest validation |
| `axios` | Sanctum SPA cookie + CSRF interceptor |
| `lucide-react` | Icon set |
| `date-fns` + `date-fns-tz` | Africa/Cairo timezone formatting |
| `firebase` | Firestore SDK for chat (mirrors `chat_message_log`) |
| `clsx`, `class-variance-authority`, `tailwind-merge` | Class composition helpers |
| `@radix-ui/react-{dialog,popover,tabs,accordion,slot}` | Headless primitives (shadcn-style) |

### Dev dependencies
| Package | Why |
|---|---|
| `typescript`, `@types/node`, `@types/react`, `@types/react-dom` | TS strict mode |
| `tailwindcss@4-beta` + `@tailwindcss/postcss` | Tailwind v4 with CSS-variable theming (driven by `/api/v1/theme/tokens`) |
| `postcss`, `autoprefixer` | PostCSS pipeline |
| `eslint`, `eslint-config-next` | Linting |
| `vitest`, `@vitejs/plugin-react` | Unit tests for theme mapper + cart reducer |
| `jsdom` | DOM environment for Vitest (token mapper, store, form helpers) |
| `@playwright/test` | E2E for all three product-type golden paths in EN + AR |
| `@axe-core/playwright` | a11y assertions in Playwright (WCAG 2 A + AA) |

**No new packages may be added without an entry above in the same PR**, per CLAUDE.md Package Discipline.

---

## 6. Order of installation

Don't `composer require` everything at once. Install in this order so each step is verifiable:

### Day 1
1. Foundation (Laravel, Sanctum, Scout, Reverb, Predis)
2. Spatie Permission + Translatable + Media Library + Tags
3. Brick/Money + Spatie Data + Spatie Model States + Spatie ActivityLog + Spatie Backup
4. Dev tools (Pest, Pint, Larastan, Collision, Faker, Mockery, Ignition, Telescope, Rector-Laravel, Scribe)

### Day 2
5. Filament v3 core + Shield + translatable plugin + media-library plugin + tags plugin + activitylog plugin + settings plugin + tiptap editor + language switch + filament-excel + fullcalendar

### Week 4 (when Discovery module starts)
6. Meilisearch SDK

### Week 5 (when Payments module starts)
7. Guzzle + Symfony HTTP Client (likely already pulled in transitively, just verify)
8. Flysystem AWS S3 v3

### Week 6 (when Communication module starts)
9. Firebase + FCM + Vonage + WhatsApp + Mailchimp

### Excel imports (W2-W3, W7)
10. Maatwebsite Excel

> **Reasoning:** installing payment/communication packages early but not configuring them costs nothing. Installing them late means risk of dependency conflicts during a critical week. This order minimizes that risk.

---

## 7. Why these, not those

A few alternatives I evaluated and the reason they're not on the list:

### Why `brick/money` and not `moneyphp/money`?
Both are good. `brick/money` has stricter type discipline, better Laravel docs in 2025, and is what Anthropic's own team has been recommending in recent talks. Either works — but **don't mix them** in one project.

### Why `maatwebsite/excel` and not `openspout/openspout` directly?
`openspout` is faster and lower memory, but Maatwebsite is a higher-level abstraction with Laravel-native validation hooks, queue integration, and per-row error reporting that match `02_Tech_Decisions.md` §12 verbatim. The performance gap doesn't matter for vendor uploads (max ~500 rows).

### Why `kreait/laravel-firebase` and not raw Firebase REST?
You're already integrating Firebase for chat (Firestore) and push (FCM). One SDK for both, with Laravel auth handling, beats two custom HTTP clients.

### Why `knuckleswtf/scribe` and not `dedoc/scramble` or hand-maintained Postman?
The Flutter mobile team needs an authoritative, machine-readable API contract that stays in sync with the Laravel codebase. Scribe generates OpenAPI + a browsable HTML reference directly from `@bodyParam` PHPDoc on Form Requests and `@response` PHPDoc on API Resources — the same annotations the InstaParty API documentation rules already mandate (see `AGENTS.md` §7 and `.specify/memory/constitution.md`). Alternatives considered:

- **`dedoc/scramble`** — promising but less mature in 2026; weaker support for translatable JSON bodies and the `ApiResponse` envelope, and its inferred-type approach fights our explicit `@bodyParam` discipline.
- **Hand-maintained Postman** — doesn't scale past ~30 endpoints and drifts from code within a sprint. We keep Postman/Bruno collections (`docs/api/collections/`) for *manual smoke testing*, not as the contract source of truth.

Scribe is dev-only — it generates static artifacts at build time. No runtime cost in production.

### Why Filament v3 and not v4 if v4 is out?
Pin to v3 for Phase 1 — Filament v4's plugin ecosystem may not have caught up by your launch window. Upgrade in Phase 2 once plugins are stable.

### Why no GraphQL?
REST + Sanctum + per-locale API Resources is what `02_Tech_Decisions.md` §14 mandates. GraphQL is a Phase 2 conversation if mobile starts asking for it.

### Why no Telescope in production?
Performance overhead and storage cost. Use it locally for debugging; let the audit log + activity log carry production observability.

---

## Maintenance

When you add a new package:

1. Justify it in chat with Claude Code (one paragraph)
2. Update this file in the same commit
3. Run `composer update` only on the new package, not globally (`composer update vendor/package`)
4. Run the full Pest suite to catch dependency-induced regressions

When you remove a package:

1. Confirm no `use` statements still reference it: `rg "Vendor\\Package" app/`
2. `composer remove vendor/package`
3. Update this file in the same commit
4. Test
