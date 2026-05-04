# API Contract: CMS Pages (Phase 6.2)

> Public API contract for `GET /api/v1/cms/pages/{slug}`.
> This is the only customer-facing endpoint in Phase 6.2. Filament mutations are internal.

---

## GET /api/v1/cms/pages/{slug}

**Route file**: `app/Modules/Shared/Routes/customer.php`
**Controller**: `app/Modules/Shared/Http/Controllers/CmsPageController.php`
**Method**: `show(CmsSlug $slug)`
**Auth**: None (public endpoint — no Sanctum required)
**Rate limit**: None in Phase 1 (Phase 7.0 hardens this)

### Path Parameters

| Parameter | Type | Required | Description |
|---|---|---|---|
| `slug` | string | YES | One of: `terms`, `privacy`, `about`, `contact` |

### Request Headers

| Header | Required | Description |
|---|---|---|
| `Accept-Language` | NO | `en` or `ar`. Defaults to `en` if absent or unsupported. |
| `Accept` | NO | `application/json` (standard) |

### Success Response — 200 OK

```json
{
  "data": {
    "slug": "terms",
    "title": "Terms & Conditions",
    "body": "<h2>Welcome to InstaParty</h2><p>By using this platform...</p>",
    "meta_description": "InstaParty Terms & Conditions for customers and vendors.",
    "published_at": "2026-05-03T10:00:00.000000Z"
  },
  "meta": {},
  "errors": null
}
```

**Arabic locale example** (`Accept-Language: ar`):

```json
{
  "data": {
    "slug": "terms",
    "title": "الشروط والأحكام",
    "body": "<h2>مرحباً بك في إنستاباrti</h2><p>باستخدامك للمنصة...</p>",
    "meta_description": "شروط وأحكام إنستاباrti للعملاء والبائعين.",
    "published_at": "2026-05-03T10:00:00.000000Z"
  },
  "meta": {},
  "errors": null
}
```

### Error Response — 404 Not Found

Returned when:
- The slug does not match any `cms_pages` row
- The matching page has `is_published = false`
- The slug value is not one of the Phase 1 valid values (route-level enum rejection)

```json
{
  "data": null,
  "meta": {},
  "errors": [
    {
      "code": "NOT_FOUND",
      "message": "Page not found."
    }
  ]
}
```

### PHP Controller Signature

```php
/**
 * @group CMS
 *
 * Get a published CMS page by slug.
 *
 * Returns the page content in the locale specified by the Accept-Language header.
 * Only published pages are returned. Unpublished pages return 404.
 *
 * @urlParam slug string required The page slug. One of: terms, privacy, about, contact. Example: terms
 *
 * @response 200 {
 *   "data": {
 *     "slug": "terms",
 *     "title": "Terms & Conditions",
 *     "body": "<p>These are the terms...</p>",
 *     "meta_description": "InstaParty Terms",
 *     "published_at": "2026-05-03T10:00:00.000000Z"
 *   },
 *   "meta": {},
 *   "errors": null
 * }
 *
 * @response 404 {
 *   "data": null,
 *   "meta": {},
 *   "errors": [{"code": "NOT_FOUND", "message": "Page not found."}]
 * }
 */
public function show(CmsSlug $slug): JsonResponse
{
    $page = CmsPage::published()->where('slug', $slug->value)->firstOrFail();
    return ApiResponse::success(new CmsPageResource($page));
}
```

### API Resource Shape

```php
// app/Modules/Shared/Http/Resources/CmsPageResource.php
public function toArray(Request $request): array
{
    $locale = app()->getLocale(); // set from Accept-Language middleware
    return [
        'slug'             => $this->slug->value,
        'title'            => $this->getTranslation('title', $locale),
        'body'             => $this->getTranslation('body', $locale),
        'meta_description' => $this->getTranslation('meta_description', $locale) ?: null,
        'published_at'     => $this->published_at?->toISOString(),
    ];
}
```

---

## Admin-Only Filament Actions (no public API)

The following are **not** REST endpoints — they are Filament form actions:

| Action | Filament Location | Description |
|---|---|---|
| Publish CMS page | `CmsPageResource` row action | Sets `is_published=true`, stamps `published_at` |
| Unpublish CMS page | `CmsPageResource` row action | Sets `is_published=false` |
| Edit page content | `CmsPageResource` edit form | TipTap EN+AR body edit |
| Update app settings | `ManageSettings` Filament page | Key-value table edit |
| Toggle feature flag | `ManageSettings` Filament page | Toggle + rollout_pct edit |

---

## api-registry.md Entry (to add)

```markdown
| `GET /api/v1/cms/pages/{slug}` | CmsPageController@show | Public | Returns published CMS page in requested locale | Phase 6.2 |
```
