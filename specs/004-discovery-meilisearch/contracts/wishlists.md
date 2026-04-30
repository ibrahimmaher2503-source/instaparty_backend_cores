# Contract: Wishlist Management

**Auth**: `auth:sanctum` — customer role required on all wishlist endpoints

---

## Add to Wishlist

**Endpoint**: `POST /api/v1/customer/wishlist/items`
**Action**: `AddToWishlistAction`

### Request

```json
{
  "service_id": "01HSERVICE..."
}
```

| Field | Rule |
|---|---|
| `service_id` | required, exists:services,public_id, status=published |

### Response — 201 Created (or 200 if already exists)

```json
{
  "data": {
    "service_id": "01HSERVICE...",
    "service_name": "نطاطة فروزن",
    "added_at": "2026-04-30T14:00:00+03:00"
  },
  "meta": {
    "wishlist_count": 5
  },
  "errors": null
}
```

### Error Responses

| Status | Condition |
|---|---|
| 401 | Unauthenticated |
| 404 | Service not found or not published |
| 422 | Missing `service_id` |

---

## Remove from Wishlist

**Endpoint**: `DELETE /api/v1/customer/wishlist/items/{servicePublicId}`
**Action**: `RemoveFromWishlistAction`

### Request

No body. `servicePublicId` in path.

### Response — 204 No Content

Empty body.

### Error Responses

| Status | Condition |
|---|---|
| 401 | Unauthenticated |
| 404 | Service not in customer's wishlist |

---

## List Wishlist Items

**Endpoint**: `GET /api/v1/customer/wishlist`

### Response — 200 OK

```json
{
  "data": [
    {
      "service_id": "01HSERVICE...",
      "name": "نطاطة فروزن",
      "product_type": "rental",
      "base_price_minor": 150000,
      "base_price_currency": "EGP",
      "thumbnail_url": "https://cdn.instaparty.com/.../thumb.jpg",
      "is_available": true,
      "added_at": "2026-04-30T14:00:00+03:00"
    }
  ],
  "meta": {
    "total": 1
  },
  "errors": null
}
```

**Notes**:
- `is_available` reflects current `services.status == published`. Archived services remain in wishlist data but `is_available = false`.
- `name` in customer's locale from `Accept-Language`.
