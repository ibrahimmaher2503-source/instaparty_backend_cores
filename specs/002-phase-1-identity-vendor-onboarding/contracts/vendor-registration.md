# Contract: Vendor Registration & Profile

**Module**: Identity
**Audience**: Flutter vendor app, Next.js vendor web
**Auth guard**: `auth:sanctum`

---

## POST /api/v1/register/vendor

Register a new vendor account.

**Request body**:
```json
{
  "name": "Ibrahim Vendor",
  "phone_e164": "+201009876543",
  "email": "vendor@example.com",
  "password": "secret_min8chars",
  "password_confirmation": "secret_min8chars",
  "business_name": {
    "en": "Happy Balloons",
    "ar": "بالونات سعيدة"
  },
  "business_type": "individual",
  "primary_governorate_id": 1,
  "primary_city_id": 5,
  "preferred_locale": "ar"
}
```
Required: `name`, `phone_e164`, `password`, `password_confirmation`, `business_name.en`, `business_name.ar`, `business_type`, `primary_governorate_id`, `primary_city_id`

**Response 201**:
```json
{
  "data": {
    "id": "01HZ...",
    "name": "Ibrahim Vendor",
    "vendor_profile": {
      "id": "01IA...",
      "business_name": "بالونات سعيدة",
      "approval_status": "pending",
      "slug": "happy-balloons"
    }
  },
  "meta": {},
  "errors": null
}
```

**Errors**: `422` validation, duplicate phone/email

---

## GET /api/v1/vendor/profile

**Auth**: Required (vendor role)
**Accept-Language**: `ar` or `en`

**Response 200**:
```json
{
  "data": {
    "id": "01IA...",
    "business_name": "بالونات سعيدة",
    "slug": "happy-balloons",
    "approval_status": "pending",
    "business_type": "individual",
    "approved_product_types": [],
    "primary_city_id": 5,
    "primary_governorate_id": 1
  },
  "meta": {},
  "errors": null
}
```

---

## PUT /api/v1/vendor/profile

**Auth**: Required (vendor role)

**Request body** (all fields optional — partial update):
```json
{
  "business_name": { "en": "Happy Balloons Co", "ar": "شركة بالونات سعيدة" },
  "bio": { "en": "...", "ar": "..." },
  "bank_iban": "EG000000000000000000000000000",
  "bank_name": "CIB"
}
```

**Response 200**: Updated vendor profile resource

---

## POST /api/v1/vendor/documents

Upload a vendor document.

**Auth**: Required (vendor role)
**Content-Type**: `multipart/form-data`

**Request fields**:
```
doc_type: cr | tax_card | national_id | iban_proof | other
file: (binary)
```

**Response 201**:
```json
{
  "data": {
    "id": "01IB...",
    "doc_type": "cr",
    "file_name": "commercial_register.pdf",
    "status": "pending"
  },
  "meta": {},
  "errors": null
}
```

**Errors**: `422` invalid doc_type, file exceeds 10 MB, MIME type not in `[application/pdf, image/jpeg, image/png]`

---

## POST /api/v1/vendor/coverage-areas

Add a city to the vendor's coverage area.

**Auth**: Required (vendor role)

**Request body**:
```json
{
  "city_id": 5,
  "delivery_fee_minor": 2000,
  "delivery_fee_currency": "EGP",
  "min_order_minor": 50000,
  "min_order_currency": "EGP"
}
```

**Response 201**: `{ "data": { "city_id": 5, "delivery_fee": "20.00 EGP", ... } }`

---

## PUT /api/v1/vendor/business-hours

Upsert weekly schedule.

**Auth**: Required (vendor role)

**Request body**:
```json
{
  "hours": [
    { "day_of_week": 0, "opens_at": null, "closes_at": null },
    { "day_of_week": 1, "opens_at": "09:00", "closes_at": "18:00" },
    { "day_of_week": 2, "opens_at": "09:00", "closes_at": "18:00" }
  ]
}
```

**Response 200**: `{ "data": { "hours": [...] } }`
