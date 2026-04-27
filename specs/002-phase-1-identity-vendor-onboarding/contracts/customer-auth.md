# Contract: Customer Auth & Registration

**Module**: Identity
**Audience**: Flutter mobile (token mode), Next.js SPA (cookie mode)
**Auth guard**: `auth:sanctum`

---

## POST /api/v1/register/customer

Register a new customer account.

**Request body**:
```json
{
  "name": "Ahmed Mohamed",
  "phone_e164": "+201001234567",
  "email": "ahmed@example.com",
  "password": "secret_min8chars",
  "password_confirmation": "secret_min8chars",
  "preferred_locale": "ar"
}
```
Required: `name`, `phone_e164`, `password`, `password_confirmation`
Optional: `email`, `preferred_locale` (default: `ar`)

**Response 201**:
```json
{
  "data": {
    "id": "01HX...",
    "name": "Ahmed Mohamed",
    "phone_e164": "+201001234567",
    "phone_verified": false,
    "preferred_locale": "ar"
  },
  "meta": {},
  "errors": null
}
```

**Errors**:
- `422` — duplicate phone/email, validation failure (field-level errors in request locale)
- `429` — rate limit (5 requests/minute per IP)

---

## POST /api/v1/phone/verify

Confirm the OTP sent to the customer's phone.

**Auth**: Required (Sanctum)

**Request body**:
```json
{ "code": "123456" }
```

**Response 200**:
```json
{
  "data": { "phone_verified": true },
  "meta": {},
  "errors": null
}
```

**Errors**: `422` invalid/expired OTP

---

## POST /api/v1/login

Authenticate and receive a token (mobile) or SPA cookie (web).

**Request body**:
```json
{
  "phone_e164": "+201001234567",
  "password": "secret_min8chars",
  "device_name": "iPhone 15"
}
```

**Response 200 (mobile — returns token)**:
```json
{
  "data": {
    "token": "1|AbCdEf...",
    "user": { "id": "01HX...", "name": "Ahmed Mohamed", "preferred_locale": "ar" }
  },
  "meta": {},
  "errors": null
}
```

**Response 200 (web SPA — sets HttpOnly cookie)**:
```json
{
  "data": {
    "user": { "id": "01HX...", "name": "Ahmed Mohamed" }
  },
  "meta": {},
  "errors": null
}
```

**Errors**: `401` wrong credentials, `422` validation

---

## POST /api/v1/logout

**Auth**: Required

**Response**: `204 No Content`

---

## GET /api/v1/customer/profile

**Auth**: Required (customer role)

**Response 200**:
```json
{
  "data": {
    "id": "01HX...",
    "name": "Ahmed Mohamed",
    "phone_e164": "+201001234567",
    "preferred_locale": "ar",
    "profile": {
      "date_of_birth": "1990-05-15",
      "gender": "male",
      "accepts_marketing": true
    }
  },
  "meta": {},
  "errors": null
}
```

---

## POST /api/v1/customer/addresses

**Auth**: Required (customer role)

**Request body**:
```json
{
  "city_id": 123,
  "label": "Home",
  "address_line": "15 Nile Street",
  "building": "Tower A",
  "floor": "3",
  "apartment": "301",
  "recipient_name": "Ahmed Mohamed",
  "recipient_phone_e164": "+201001234567",
  "is_default": true
}
```

**Response 201**:
```json
{
  "data": { "id": "01HY...", "label": "Home", "city_id": 123, "is_default": true },
  "meta": {},
  "errors": null
}
```
