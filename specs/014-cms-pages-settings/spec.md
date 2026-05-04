# Feature Specification: CMS Pages + Settings

**Phase**: 6.2 — CMS Pages + Settings  
**Feature Branch**: `014-cms-pages-settings`  
**Created**: 2026-05-03  
**Status**: Draft  
**Module**: Shared (Cross-cutting)  
**Week**: 7 — 1 day

---

## Phase Identity

| Field | Value |
|---|---|
| Phase ID | `6.2` (per `docs/specs/09_Phasing_Plan.md`) |
| PRD Coverage | Admin Journey §6.3 ("manage platform settings"), Software Description §5 (CMS pages) |
| Tables Touched | `cms_pages`, `app_settings`, `feature_flags` |
| ADR Required | No new module — Shared module ADR already established. Cross-cutting tables own this scope. Flag if ADR-Shared doesn't exist. |
| Cut-List | None (per phasing plan) |

---

## Goal

Admin can create, edit, publish, and manage static CMS pages (Terms, Privacy, About, Contact) in **both English and Arabic** using a TipTap rich-text editor in Filament. App settings and feature flags are configurable from a Filament settings panel. The customer-facing API returns only **published** pages in the **requested locale**.

---

## User Scenarios & Testing

### User Story 1 — Admin Edits and Publishes a CMS Page (Priority: P1)

An admin opens the Terms & Conditions page in Filament, edits the English and Arabic body content using the TipTap rich-text editor, and publishes it. The customer-facing API immediately serves the updated content in the correct locale.

**Why this priority**: The published-page flow is the core deliverable of Phase 6.2. Everything else depends on pages existing and being publishable.

**Independent Test**: Can be fully tested by creating a `terms` page, publishing it, calling `GET /api/v1/cms/pages/terms` with `Accept-Language: ar`, and asserting the Arabic body is returned.

**Acceptance Scenarios**:

1. **Given** an admin is on the CMS Pages list, **When** they open the `terms` record, edit the English and Arabic body via TipTap, and click Save, **Then** both locales are persisted in the JSON `body` column.
2. **Given** a CMS page with `is_published = false`, **When** the admin clicks "Publish", **Then** `is_published` is set to `true`, `published_at` is stamped, and `updated_by` captures the admin's user ID.
3. **Given** a published CMS page with `slug = terms`, **When** `GET /api/v1/cms/pages/terms` is called with `Accept-Language: en`, **Then** the response returns the English `title`, `body`, `meta_description`, and `published_at`.
4. **Given** a published CMS page, **When** `GET /api/v1/cms/pages/terms` is called with `Accept-Language: ar`, **Then** the response returns the Arabic variants of all translatable fields.

---

### User Story 2 — Customer API Returns Only Published Pages (Priority: P2)

A customer app calls the CMS API. The API must silently reject requests for unpublished pages and return a 404 rather than exposing draft content.

**Why this priority**: Prevents accidental exposure of draft legal text (Terms, Privacy) which could cause compliance issues.

**Independent Test**: Create a draft page (unpublished), call the public endpoint, assert 404. Publish it, call again, assert 200.

**Acceptance Scenarios**:

1. **Given** a `cms_pages` record with `is_published = false`, **When** `GET /api/v1/cms/pages/{slug}` is called, **Then** the response is `404 Not Found`.
2. **Given** no page exists for the requested `slug`, **When** the endpoint is called, **Then** the response is `404 Not Found`.
3. **Given** a published page, **When** the admin sets `is_published = false`, **Then** subsequent API calls return `404`.

---

### User Story 3 — Admin Configures App Settings (Priority: P3)

An admin navigates to the Settings panel in Filament and updates key-value application settings (e.g., platform commission defaults, vendor SLA hours, contact email). The settings are stored and retrievable by the backend at runtime.

**Why this priority**: Settings are cross-phase infrastructure needed for Phase 7.0 hardening and Phase 8.x admin governance, but the CMS page flow works without settings being editable day one.

**Independent Test**: Can be fully tested by updating a setting key via Filament and asserting the new value is persisted in `app_settings`.

**Acceptance Scenarios**:

1. **Given** the Settings panel is loaded, **When** an admin updates a setting value, **Then** the row in `app_settings` is updated with the new JSON value and `updated_by` is captured.
2. **Given** the Settings panel, **When** an admin toggles a feature flag on/off, **Then** `feature_flags.is_enabled` reflects the new state.
3. **Given** a feature flag with `rollout_pct = 50`, **When** viewed in the Filament settings panel, **Then** the rollout percentage is displayed and editable.

---

### User Story 4 — Admin Unpublishes a Page (Priority: P2)

An admin needs to take down a page temporarily (e.g., Privacy Policy is being revised). They click "Unpublish" and the page disappears from the public API.

**Why this priority**: Required for content lifecycle management — publish and unpublish are the two sides of the same flow.

**Acceptance Scenarios**:

1. **Given** a published CMS page, **When** admin clicks "Unpublish", **Then** `is_published` is set to `false` and the API returns `404` for that slug.
2. **Given** an unpublished page, **When** admin clicks "Publish", **Then** `is_published = true` and `published_at` is refreshed to `now()`.

---

### Edge Cases

- What happens when a CMS page's `body` JSON is missing the requested locale key? — Return the other available locale as fallback, or empty string if neither exists.
- What happens when `slug` is called with a value not in (`terms`, `privacy`, `about`, `contact`)? — Return 404. No dynamic slug creation in Phase 1.
- What happens when `app_settings.value` is a complex nested JSON? — Store as-is; the backend reads with `Arr::get($setting->value, 'key.path')`. Filament shows raw JSON textarea for complex values.
- What happens when `feature_flags.rollout_pct` is set to 0? — Flag is effectively disabled regardless of `is_enabled`. The system evaluates `is_enabled AND rollout_pct > 0`.
- What happens when both EN and AR bodies are empty strings? — Validation must prevent saving if either locale's body is blank when the page is being published.

---

## Requirements

### Functional Requirements

- **FR-CMS-001**: Admin MUST be able to create, read, update, and soft-publish CMS pages via Filament (`cms_pages` table).
- **FR-CMS-002**: Each CMS page MUST support bilingual content: `title` (JSON), `body` (JSON), and `meta_description` (JSON) in both `en` and `ar` locales.
- **FR-CMS-003**: Admin MUST edit `body` content using the TipTap rich-text editor (`awcodes/filament-tiptap-editor`) with separate EN and AR tabs.
- **FR-CMS-004**: Pages MUST have a publish/unpublish action that sets `is_published`, stamps `published_at`, and records `updated_by`.
- **FR-CMS-005**: The public API endpoint `GET /api/v1/cms/pages/{slug}` MUST return only published pages in the locale specified by the `Accept-Language` request header.
- **FR-CMS-006**: The API endpoint MUST return `404` for any slug that does not exist or is not published.
- **FR-CMS-007**: Phase 1 slugs are exactly: `terms`, `privacy`, `about`, `contact`. No other slug values are seeded or validated in Phase 1.
- **FR-CMS-008**: Admin MUST be able to view and update `app_settings` key-value pairs via a Filament Settings page (`filament/spatie-laravel-settings-plugin`).
- **FR-CMS-009**: Admin MUST be able to toggle `feature_flags` on/off and adjust `rollout_pct` via Filament.
- **FR-CMS-010**: Every write operation (publish, unpublish, settings update) MUST record the acting admin's user ID in `updated_by`.
- **FR-CMS-011**: Validation MUST prevent publishing a page with an empty EN or AR body.

### Key Entities

- **CmsPage**: A static content page uniquely identified by `slug`. Has translatable `title`, `body`, `meta_description`. Has a binary publish state with timestamp. Belongs to the Shared module.
- **AppSetting**: A key-value configuration row. Key is unique. Value is JSON (scalar or nested). Editable by admin only.
- **FeatureFlag**: A named boolean + rollout percentage toggle. Used by backend runtime to gate experimental features. Not exposed to customers in Phase 1 API.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: Admin can open the Terms page, update both EN and AR body content, publish it, and confirm the API returns the updated content — all within 3 minutes of navigation.
- **SC-002**: The public CMS API returns `404` with 100% reliability for any unpublished page slug (zero false positives in test suite).
- **SC-003**: The CMS Filament resource correctly applies `spatie/laravel-translatable` — both locales are stored and independently retrievable without data loss after a save.
- **SC-004**: The Settings panel reflects the current `app_settings` and `feature_flags` state on load with no stale cache.
- **SC-005**: All Pest tests pass with locale assertions in both `en` and `ar` for the public API endpoint.

---

## Constitution Check

| Principle | Applies? | Status | Notes |
|---|---|---|---|
| I. Modular Monolith | YES | ✅ PASS | CMS lives in `app/Modules/Shared/` per the cross-cutting table ownership rule in schema-cheatsheet. No microservice boundary. |
| II. Three Product Types (`match($enum)`) | NO | N/A | CMS pages are not product-type-aware. No `match($enum)` needed. |
| III. Money Discipline | NO | N/A | No money columns in CMS or Settings tables. |
| IV. Bilingual EN+AR | YES | ✅ PASS | `title`, `body`, `meta_description` are JSON columns via `spatie/laravel-translatable`. Both locales required on publish. API Resource converts to `App::getLocale()`. |
| V. Append-Only Tables | NO | N/A | `cms_pages`, `app_settings`, `feature_flags` are NOT append-only (they are mutable settings). `wallet_ledger`, `audit_logs`, etc. are not touched. |
| VI. ADR Before Code | PARTIAL | ⚠️ FLAG | Cross-cutting module has no dedicated ADR. Tables belong to `Shared`. If `Shared` module has no ADR, create a brief ADR-Shared before writing migrations. Alternatively, this lives under an existing Shared module scope — confirm with Ibrahim. |
| VII. Test-First (critical paths) | YES | ✅ PASS | Pest tests required: published-only filter, locale resolution (EN + AR), 404 on missing slug, 404 on unpublished slug. |
| VIII. Idempotency | NO | N/A | The CMS API endpoint is read-only (`GET`). No state-mutating customer-facing endpoints. Admin mutations via Filament don't require `Idempotency-Key` (internal UI). |
| IX. Domain Events `DB::afterCommit` | MINIMAL | ✅ PASS | A `CmsPagePublished` event may be desirable (for cache invalidation hooks) but is not required in Phase 1. No listeners that call external services inline. |
| X. Vendor Approval Two-Step | NO | N/A | CMS has no vendor-facing concept. |
| XI. Document Storage | NO | N/A | CMS body is stored as JSON in the database, not as S3 files. No media uploads in Phase 1 CMS. |

---

## Bilingual Content Notes

| Field | Column Type | Required in both locales? | Notes |
|---|---|---|---|
| `cms_pages.title` | `json` | YES — both locales required on publish | Stored as `{"en": "Terms", "ar": "الشروط"}` |
| `cms_pages.body` | `json` | YES — both locales required on publish | HTML content from TipTap editor |
| `cms_pages.meta_description` | `json` | NO — optional | Falls back to empty string if missing |

API Resource must call `$page->getTranslation('title', app()->getLocale())` (and same for `body`, `meta_description`) — single-locale response, not the full JSON object.

---

## Package Constraints

All packages used in this phase are already on `docs/specs/10_Package_List.md`:

| Package | Purpose |
|---|---|
| `awcodes/filament-tiptap-editor:^3.4` | Rich-text editor for `body` field in EN and AR tabs |
| `filament/spatie-laravel-settings-plugin:^3.2` | Settings panel for `app_settings` and `feature_flags` |
| `filament/spatie-laravel-translatable-plugin:^3.2` | EN/AR tabs on CMS page form |
| `spatie/laravel-translatable` | `$translatable` array on `CmsPage` model |

No new packages required. ✅

---

## API Contract

### `GET /api/v1/cms/pages/{slug}`

- **Auth**: None (public endpoint)
- **Locale**: Determined by `Accept-Language` header (`en` or `ar`, defaults to `en`)
- **Success 200**:
  ```json
  {
    "data": {
      "slug": "terms",
      "title": "Terms & Conditions",
      "body": "<p>These are the terms...</p>",
      "meta_description": "InstaParty Terms and Conditions",
      "published_at": "2026-05-03T10:00:00Z"
    },
    "meta": {},
    "errors": null
  }
  ```
- **404**: Page does not exist or is not published.
- **Route file**: `app/Modules/Shared/Routes/customer.php`

---

## Assumptions

- The Shared module (`app/Modules/Shared/`) already exists from Phase 0.0 (it hosts `MoneyCast`, shared contracts, etc.). CMS migrations and models live there.
- Phase 1 CMS slugs are hardcoded to `terms`, `privacy`, `about`, `contact`. Admin cannot create new slugs from Filament; only these four are seeded.
- The `filament/spatie-laravel-settings-plugin` settings page will manage `app_settings` and `feature_flags` directly via Filament's Key-Value or custom schema approach — not through a typed Settings DTO pattern, since the values are arbitrary JSON.
- `feature_flags` are not yet consumed by any Phase 1 API endpoint feature-gate logic; they are set up structurally now for Phase 7.0/8.x use.
- The admin CMS page does not require audit log entries in the `audit_logs` table for Phase 1 (only `updated_by` is captured). Full audit trail via `spatie/laravel-activitylog` can be added in Phase 7.0.
- No `public_id` is needed on `app_settings` or `feature_flags` since they are internal key-based entities, not URL-exposed resources.
- The public CMS API does not require rate limiting in Phase 1 (added in Phase 7.0 hardening).
