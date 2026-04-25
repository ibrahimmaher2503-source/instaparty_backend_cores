# InstaParty — Full Bilingual (EN/AR) Support Specification

## 1. Bilingual Scope — What "Bilingual" Means in This Project

The platform must operate in two first-class languages with full feature parity:

- **Arabic (`ar`)** — primary language, RTL layout, Egyptian/MSA tone
- **English (`en`)** — secondary language, LTR layout

Both languages are first-class — no "translated afterthought" UX. Every user (customer, vendor, admin) can switch language at any time without losing state, and every piece of user-visible content has both EN and AR variants.

## 2. Language Support Matrix — Every Layer Covered

| Layer | EN/AR Support | Implementation |
|---|---|---|
| UI strings (buttons, labels, errors, placeholders) | ✅ | Laravel `lang/en/*.php` + `lang/ar/*.php`; Flutter `intl` ARB files |
| Catalog content (categories, occasions, services, products, vendor names) | ✅ | Translation tables in DB, one row per language |
| User-generated content (service descriptions, vendor bios, customer notes) | ✅ | Vendors enter both EN and AR; customers enter in their chosen language |
| Reviews and ratings | ✅ | Stored in original language; language code tagged on each review |
| Chat messages (Firebase) | ✅ | Stored as-is; no translation; UI direction follows message language |
| Push notifications, WhatsApp, SMS, email | ✅ | Templates per language; user's preferred language drives template selection |
| Marketing campaigns | ✅ | Admin defines campaign per language; recipients targeted by language preference |
| Excel bulk upload templates | ✅ | Two columns per translatable field (`name_en`, `name_ar`, `description_en`, `description_ar`) |
| Admin panel (Filament) | ✅ | Filament locale switcher; admin can manage content in both languages |
| PDF/email receipts and invoices | ✅ | Generated in user's preferred language with correct RTL/LTR direction |
| Error messages and validation | ✅ | Laravel localized validation messages; API returns localized error responses |
| Date, time, currency, number formatting | ✅ | Locale-aware formatting (Arabic numerals optional; Hijri date support optional) |
| SEO metadata (web only) | ✅ | Per-locale meta tags, hreflang tags, sitemap per language |
| URLs (web only) | ✅ | Locale prefix: `/en/...` and `/ar/...` |

## 3. Backend (Laravel) — Bilingual Architecture

### 3.1 Translatable Database Strategy

Use the `spatie/laravel-translatable` package (industry standard, integrates cleanly with Filament). Pattern: translatable fields stored as JSON columns.

```php
// Migration example
$table->json('name');           // {"en": "Birthday Cake", "ar": "تورتة عيد ميلاد"}
$table->json('description');    // {"en": "...", "ar": "..."}
```

**Translatable entities:**

- `occasions` (birthday, wedding, engagement)
- `categories` and subcategories
- `services` (name, short description, long description, theme)
- `products`
- `vendors` (display name, bio, address)
- `cities`, `regions`, `governorates`
- `loyalty_program` rules and labels
- `terms_and_conditions`, `privacy_policy`, `about_us`, `contact_us`
- `notification_templates`
- `campaign_templates`
- `email_templates`
- `category_specific_form_field_labels`

**Non-translatable (single-value):**

Prices, IDs, dates, coordinates, status enums, counts, vendor banking details.

### 3.2 Locale Detection and Resolution

**Order of precedence:**

1. Explicit query parameter (`?lang=ar`) or header (`Accept-Language`)
2. Authenticated user's saved `preferred_locale` column on the `users` table
3. Browser `Accept-Language` header
4. App default (Arabic)

Implemented as a Laravel middleware that runs early in the request lifecycle and calls `app()->setLocale()`.

### 3.3 API Localization Strategy

Two parallel mechanisms depending on caller need:

**Mechanism A — Single-locale response (default for mobile apps):**

Caller sends `Accept-Language: ar` header. API Resource returns only the requested locale's value:

```json
{ "id": 1, "name": "تورتة عيد ميلاد", "description": "..." }
```

**Mechanism B — Multi-locale response (for admin and vendor edit screens):**

Caller sends `?translations=all` query param. API Resource returns all locales:

```json
{ "id": 1, "name": { "en": "Birthday Cake", "ar": "تورتة عيد ميلاد" } }
```

This pattern is essential because vendors and admins need to edit both languages simultaneously — they shouldn't have to switch app locale to update the AR version of a service.

### 3.4 Validation Messages

- Laravel default validation messages localized via `lang/en/validation.php` and `lang/ar/validation.php`
- Custom validation messages per Form Request, also bilingual
- API error responses return localized `message` field with `errors[]` array also localized

### 3.5 Database Collation

- Use `utf8mb4` charset and `utf8mb4_unicode_ci` collation for proper Arabic sorting and search
- Search indexes (Laravel Scout / Meilisearch) configured with **Arabic tokenizer** and **English tokenizer** so users can search in either language and find results in either language

## 4. Filament Admin Panel — Bilingual Authoring

### 4.1 Filament Localization Setup

- Install `filament/spatie-laravel-translatable-plugin` for native translatable resource support
- Admin user has a `preferred_locale` setting; UI chrome (menus, buttons, tooltips) follows it
- Locale switcher in the top bar

### 4.2 Translatable Resource Pattern

Every resource that manages translatable content shows language tabs in forms:

```
[ English ] [ العربية ]
```

Each tab contains the translatable fields for that language. Required-field validation enforces that both languages are filled before save (configurable per resource).

**Resources requiring bilingual authoring:**

- Occasions, Categories, Services (admin override edits), Vendors (display name only), Cities, Regions
- Notification templates, Email templates, SMS templates, WhatsApp templates, Push templates
- CMS pages (Terms, Privacy, About, Contact)
- Loyalty program rules

### 4.3 Filament Tables — Translatable Display

Tables show the value in the admin's current locale, with a small flag indicator if a translation is missing for any record.

## 5. Vendor Experience — Bilingual Content Authoring

Vendors must enter their service catalog in both languages. The vendor app and vendor web flow handle this as follows:

### 5.1 Service Creation Form

- Tabbed UI: `English | العربية`
- Required fields per language: name, short description, long description
- Shared fields (one entry only): price, images, video link, category, size, weight, suggested age, theme tag, electricity requirement, rental duration
- Save button is disabled until both language tabs have all required fields filled
- Inline character count and translation hints (e.g., "Tip: Arabic descriptions tend to be 20% shorter")

### 5.2 Excel Bulk Upload — Bilingual Template

Each category-specific Excel template includes paired columns for translatable fields:

| Column | Required | Notes |
|---|---|---|
| `code` | ✅ | Unique vendor SKU |
| `category` | ✅ | Category key |
| `name_en` | ✅ | English name |
| `name_ar` | ✅ | Arabic name |
| `short_description_en` | ✅ | |
| `short_description_ar` | ✅ | |
| `long_description_en` | ✅ | |
| `long_description_ar` | ✅ | |
| `theme_en` | optional | |
| `theme_ar` | optional | |
| `price` | ✅ | Single value |
| `size`, `weight`, `age`, ... | varies | Per category |

**Validation rules:**

- Both `_en` and `_ar` variants must be present and non-empty for required fields
- Per-row error reporting in the user's chosen language
- Failed rows reported with line number and specific reason
- **No partial commits** (locked Tech Decisions §12)

### 5.3 Translation Helper (Optional, Phase 1.5)

To reduce vendor friction, an optional "auto-translate suggestion" button can call a translation service to draft the second language (vendor reviews and saves). Not required for Phase 1, but the data model leaves room for it.

## 6. Customer Experience — Locale Selection

### 6.1 First-Run Experience

- **Mobile app first launch:** language picker screen (Arabic / English) before login
- **Web first visit:** language detected from `Accept-Language`, with a switcher in the header
- Choice persisted to device storage and to the user's profile after sign-in

### 6.2 In-Session Switching

- Language switcher available at all times (header on web, settings on mobile)
- Switching language **does not lose state** — current cart, draft booking, scroll position preserved
- Switching triggers re-fetch of localized content from the API

### 6.3 Customer-Generated Content

- Customer notes attached to bookings: stored as-is in whatever language the customer typed
- Reviews: stored as-is, with a locale tag
- Display: reviews show in original language; optional "translate" toggle (Phase 1.5)

## 7. Mobile Apps (Flutter) — Bilingual Implementation

### 7.1 Flutter Localization Setup

- Use Flutter's official `intl` package + `flutter_localizations`
- ARB files: `lib/l10n/app_en.arb`, `lib/l10n/app_ar.arb`
- Generated `AppLocalizations` class via `flutter gen-l10n`

### 7.2 RTL/LTR Handling

- `MaterialApp.localizationsDelegates` and `supportedLocales` configured for both
- `Directionality` widget applied at root, driven by current locale
- All custom widgets use logical edge insets (`EdgeInsetsDirectional.only(start:, end:)`) — never `left`/`right`
- Icons that have direction (back arrow, chevron, send, swipe indicators) flip automatically with `Directionality`
- `TextAlign.start` and `TextAlign.end` used everywhere — never `TextAlign.left`/`right`

### 7.3 Fonts

- **Arabic:** a high-quality Arabic font with full diacritic support (recommended: Cairo, Tajawal, or IBM Plex Sans Arabic)
- **English:** a complementary Latin font (recommended: Inter or Poppins)
- Font selection switches with locale; line height tuned for Arabic readability (Arabic glyphs typically need ~1.6× line height)

### 7.4 Number, Date, Currency Formatting

- **Numerals:** use Western Arabic numerals (0-9) by default in both locales for clarity in pricing. Eastern Arabic numerals (٠-٩) configurable as a user preference
- **Currency:** EGP (`ج.م` in AR, `EGP` in EN), with locale-correct decimal/thousands separators
- **Dates:** Gregorian by default. `intl.DateFormat.yMMMd(locale)` for locale-aware month names
- **Times:** 12-hour with localized AM/PM (`ص`/`م` in AR)

### 7.5 GetX Locale Management

```dart
Get.updateLocale(Locale('ar'));  // Triggers full app re-render
```

Locale stored in SharedPreferences and synced to the user profile via API.

## 8. Web Frontend — Bilingual Implementation

### 8.1 URL Strategy

- Locale prefix in URL: `/en/...` and `/ar/...`
- Default redirect to `/ar/` for unauthenticated users (configurable)
- Language switcher swaps the prefix, preserving the rest of the path

### 8.2 SEO

- `<html lang="ar" dir="rtl">` or `<html lang="en" dir="ltr">` per page
- `<link rel="alternate" hreflang="ar" href=".../ar/..." />` and `hreflang="en"` on every page
- Per-locale sitemaps: `/sitemap-en.xml`, `/sitemap-ar.xml`
- Localized meta tags, Open Graph tags, Twitter Card tags

### 8.3 RTL CSS

- Tailwind RTL plugin (`tailwindcss-rtl`) or logical properties (`margin-inline-start`, `padding-inline-end`) throughout
- No hardcoded `left`/`right` in custom CSS
- Icons and directional UI elements flipped via `[dir="rtl"]` selectors or logical CSS

### 8.4 Forms

- Input direction follows `<html dir>`
- For mixed-content fields (e.g., a customer typing English in an Arabic UI), input direction can be set to `auto` so the input flows in the language being typed

## 9. Notifications and Communications — Per-Language Templates

### 9.1 Template Storage

Each notification, email, SMS, WhatsApp, and push template has two versions (EN and AR), stored as a translatable Filament resource.

```php
'order_modified' => [
  'en' => [
    'title' => 'Your order was modified',
    'body'  => 'Vendor {vendor} updated your order. Please review.',
  ],
  'ar' => [
    'title' => 'تم تعديل طلبك',
    'body'  => 'قام المورد {vendor} بتعديل طلبك. برجاء المراجعة.',
  ],
]
```

### 9.2 Send-Time Locale Selection

The notification dispatcher reads the recipient user's `preferred_locale` and selects the matching template.

### 9.3 Marketing Campaigns

Admin creates each campaign in one or both languages and targets:

- All users in language X, **or**
- All users (system picks template per user's preferred locale)

This is enforced in the campaign builder UI.

### 9.4 RTL in Email

- Email templates are built with `dir="rtl"` for Arabic and `dir="ltr"` for English
- Tables and layouts use `align="start"` and inline CSS `direction:` attribute
- Tested across Gmail, Outlook, Apple Mail, and Egyptian email clients

## 10. Chat (Firebase) — Mixed-Language Handling

- Chat messages stored as typed, with a `detected_locale` field (auto-detected from message content)
- **Bubble direction follows the message's own language**, not the UI direction — so Arabic messages always render RTL even if the UI is English, and vice versa
- Voice notes have no language; transcription is out of Phase 1 scope
- Phone/email regex blocker works on both English and Arabic-script phone numbers and emails

## 11. Database Schema Summary — Bilingual Fields

```sql
-- Users
ALTER TABLE users ADD COLUMN preferred_locale ENUM('en', 'ar') DEFAULT 'ar';

-- Translatable JSON pattern (used across many tables)
ALTER TABLE occasions ADD COLUMN name JSON;             -- {"en":"Birthday","ar":"عيد ميلاد"}
ALTER TABLE categories ADD COLUMN name JSON;
ALTER TABLE services ADD COLUMN name JSON;
ALTER TABLE services ADD COLUMN short_description JSON;
ALTER TABLE services ADD COLUMN long_description JSON;
-- ...and so on for every translatable field

-- Reviews keep their original locale
ALTER TABLE reviews ADD COLUMN locale ENUM('en', 'ar') NOT NULL;

-- Chat messages
ALTER TABLE chat_messages ADD COLUMN detected_locale ENUM('en', 'ar', 'mixed');
```

## 12. Testing Requirements for Bilingual Support

- Every UI screen tested in both languages and both directions
- Snapshot tests in Flutter for both locales
- Visual regression tests on web for RTL/LTR layouts
- Notification dispatch tests: assert correct template selected per user locale
- Excel import tests: assert validation rejects rows missing either language
- Search tests: assert Arabic query finds Arabic content; English query finds English content; mixed corpus search returns both
- Email rendering tests across major clients in both languages

## 13. Bilingual Coverage Verification Checklist

| Area | Covered |
|---|---|
| UI strings (Laravel + Flutter) | ✅ |
| Catalog translatable content (DB JSON columns) | ✅ |
| API single-locale and multi-locale responses | ✅ |
| Validation messages | ✅ |
| Filament admin translatable authoring | ✅ |
| Vendor service form (tabbed EN/AR) | ✅ |
| Vendor Excel template (paired `_en`/`_ar` columns) | ✅ |
| Customer locale selection and persistence | ✅ |
| Customer-generated content storage | ✅ |
| Flutter `intl` setup + RTL/LTR | ✅ |
| Flutter logical edge insets, fonts, formatting | ✅ |
| Web URL prefix, SEO hreflang, RTL CSS | ✅ |
| Notification, email, SMS, WhatsApp, push templates per language | ✅ |
| Marketing campaign per-language targeting | ✅ |
| Chat mixed-language bubble direction | ✅ |
| DB schema (`preferred_locale`, JSON fields, review locale) | ✅ |
| Testing across both locales | ✅ |
