# Contract: Search Services

**Endpoint**: `GET /api/v1/customer/services`
**Auth**: Public (no auth required for search)
**Action**: `SearchServicesAction`

---

## Request

**Query parameters**:

| Param | Type | Description |
|---|---|---|
| `q` | string | Full-text search query (EN or AR) |
| `type` | enum | `rental`, `sale`, or `digital` |
| `category` | string | Category `public_id` |
| `occasion` | string | Occasion `code` (e.g., `birthday`) |
| `vendor` | string | Vendor profile `public_id` |
| `price_max` | integer | Max price in minor units (piastres) |
| `sort` | enum | `price_asc`, `price_desc`, `rating`, `newest` |
| `page` | integer | Page number (default 1) |
| `per_page` | integer | Results per page (default 20, max 50) |

**Example**:
```
GET /api/v1/customer/services?occasion=birthday&type=rental&q=نطاطية&per_page=20
Accept-Language: ar
```

---

## Response — 200 OK

```json
{
  "data": [
    {
      "public_id": "01HSERVICE...",
      "name": "نطاطة فروزن",
      "short_description": "نطاطة مخصصة لحفلات الأطفال بتصميم فروزن",
      "product_type": "rental",
      "base_price_minor": 150000,
      "base_price_currency": "EGP",
      "rating_avg": 4.8,
      "vendor": {
        "public_id": "01HVENDOR...",
        "business_name": "Magic Inflatables",
        "rating_avg": 4.9
      },
      "thumbnail_url": "https://cdn.instaparty.com/.../thumb.jpg",
      "is_wishlisted": false
    }
  ],
  "meta": {
    "total": 143,
    "current_page": 1,
    "per_page": 20,
    "last_page": 8,
    "query": "نطاطية",
    "filters_applied": {
      "type": "rental",
      "occasion": "birthday"
    }
  },
  "errors": null
}
```

**Notes**:
- `name` and `short_description` are in the locale from `Accept-Language` header.
- `is_wishlisted` is `false` for unauthenticated requests; `true/false` per customer if authenticated.
- Results filtered to `is_active = true` (published services only) by default.

---

## Error Responses

| Status | Condition |
|---|---|
| 422 | Invalid `type` enum value |
| 503 | Meilisearch unavailable (graceful degradation: empty results with error message) |
