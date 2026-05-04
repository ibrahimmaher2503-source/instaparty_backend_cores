# Data Model: CMS Pages + Settings (Phase 6.2)

> Phase 1 output for `/speckit.plan` — entities, fields, relationships, validation rules.
> All tables are locked in `docs/specs/11_DB_Schema.md` §14. No new tables created.

---

## Entity 1: CmsPage

**Table**: `cms_pages`
**Module**: `app/Modules/Shared/Domain/Models/CmsPage.php`
**Soft Deletes**: NO (not in the soft-delete list in CLAUDE.md §14)
**Append-Only**: NO (editable settings — NOT in the append-only list)

### Columns

| Column | PHP Type | Cast | Validation |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | — | Auto-increment |
| `public_id` | string CHAR(26) | — | ULID, unique, not user-editable |
| `slug` | string | `CmsSlug` enum cast | Required, unique, one of: `terms`, `privacy`, `about`, `contact` |
| `title` | array (JSON) | spatie/translatable | Required EN+AR both non-empty on publish |
| `body` | array (JSON) | spatie/translatable | Required EN+AR both non-empty on publish; HTML from TipTap |
| `meta_description` | array (JSON) | spatie/translatable | Optional, can be empty |
| `is_published` | bool | boolean | Required, default false |
| `published_at` | Carbon\|null | datetime | Nullable; set when published |
| `updated_by` | int\|null FK→users | — | Set on every save |
| `created_at` | Carbon | datetime | — |
| `updated_at` | Carbon | datetime | — |

### Model Declaration

```php
protected $translatable = ['title', 'body', 'meta_description'];

protected $casts = [
    'slug'         => CmsSlug::class,
    'is_published' => 'boolean',
    'published_at' => 'datetime',
];

// Scope
public function scopePublished(Builder $query): Builder
{
    return $query->where('is_published', true);
}
```

### Relationships

None (standalone entity, no FK to other module models).

---

## Entity 2: AppSetting

**Table**: `app_settings`
**Module**: `app/Modules/Shared/Domain/Models/AppSetting.php`
**Soft Deletes**: NO
**Append-Only**: NO (mutable settings)

### Columns

| Column | PHP Type | Cast | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | — | Auto-increment |
| `key` | string VARCHAR(120) | — | Unique, not user-editable from API |
| `value` | mixed (JSON) | `json` cast | Arbitrary scalar or nested JSON |
| `description` | string\|null | — | Human-readable description for admin UI |
| `updated_by` | int\|null FK→users | — | Captured on every write |
| `created_at` | Carbon | datetime | — |
| `updated_at` | Carbon | datetime | — |

### Phase 1 Seeded Keys

| Key | Type | Default | Description |
|---|---|---|---|
| `platform.commission_default_bps` | int | `1000` | Default commission in basis points (10%) |
| `platform.vendor_sla_hours` | int | `24` | Vendor response SLA in hours |
| `platform.support_email` | string | `support@instaparty.com` | Support contact email |
| `platform.min_withdrawal_minor` | int | `10000` | Minimum withdrawal (100 EGP in piastres) |

### Model Declaration

```php
protected $casts = [
    'value' => 'json',
];
```

---

## Entity 3: FeatureFlag

**Table**: `feature_flags`
**Module**: `app/Modules/Shared/Domain/Models/FeatureFlag.php`
**Soft Deletes**: NO
**Append-Only**: NO

### Columns

| Column | PHP Type | Cast | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | — | Auto-increment |
| `key` | string VARCHAR(120) | — | Unique, snake_case convention |
| `is_enabled` | bool | boolean | Toggle on/off |
| `rollout_pct` | int TINYINT | integer | 0–100; 0 = disabled regardless of `is_enabled` |
| `description` | string\|null | — | Human-readable for admin UI |
| `created_at` | Carbon | datetime | — |
| `updated_at` | Carbon | datetime | — |

### Phase 1 Seeded Flags

| Key | Default Enabled | Rollout % | Description |
|---|---|---|---|
| `features.dual_approval_wallets` | false | 0 | Require two-admin approval for wallet adjustments above threshold |
| `features.loyalty_redemption` | true | 100 | Allow loyalty point redemption at checkout |
| `features.digital_refund_override` | false | 0 | Allow admin to override digital refund policy |

### Runtime Usage Pattern

```php
// How other modules read a flag (via Shared contract):
$enabled = FeatureFlag::where('key', 'features.loyalty_redemption')
    ->where('is_enabled', true)
    ->where('rollout_pct', '>', 0)
    ->exists();
```

---

## CmsSlug Enum

**File**: `app/Modules/Shared/Domain/Enums/CmsSlug.php`

```php
enum CmsSlug: string
{
    case Terms   = 'terms';
    case Privacy = 'privacy';
    case About   = 'about';
    case Contact = 'contact';
}
```

---

## Actions

### PublishCmsPageAction

**File**: `app/Modules/Shared/Application/Actions/PublishCmsPageAction.php`

- Input: `CmsPage $page`
- Validates: EN body non-empty, AR body non-empty
- Wraps in `DB::transaction`
- Sets `is_published = true`, `published_at = now()`, `updated_by = auth()->id()`
- Returns: `CmsPage`

### UnpublishCmsPageAction

**File**: `app/Modules/Shared/Application/Actions/UnpublishCmsPageAction.php`

- Input: `CmsPage $page`
- Wraps in `DB::transaction`
- Sets `is_published = false`, `updated_by = auth()->id()`
- Returns: `CmsPage`

---

## Migration Dependency Order

These migrations are group 14 in `11_DB_Schema.md` migration order — after Imports:

1. `xxxx_create_cms_pages_table.php`
2. `xxxx_create_app_settings_table.php`
3. `xxxx_create_feature_flags_table.php`

No foreign keys to other modules. Independent of module 1–13 migrations (except `users` FK on `updated_by`).

---

## Validation Rules Summary

| Field | Rule |
|---|---|
| `CmsPage.slug` | Required, `in:terms,privacy,about,contact`, unique per DB |
| `CmsPage.title.en` | Required on publish, min:1 |
| `CmsPage.title.ar` | Required on publish, min:1 |
| `CmsPage.body.en` | Required on publish, min:1 |
| `CmsPage.body.ar` | Required on publish, min:1 |
| `CmsPage.meta_description.*` | Optional |
| `AppSetting.key` | Required, unique, snake_case via regex |
| `AppSetting.value` | Required, must be valid JSON |
| `FeatureFlag.rollout_pct` | Required, integer 0–100 |
