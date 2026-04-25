---
description: Trace a full technical flow for a user journey
argument-hint: <flow-name> (e.g. CustomerBookingFlow)
---

# Trace Flow

You're tracing: **$ARGUMENTS**

## Required reading

1. `docs/specs/06_Customer_Journey.md` (if customer flow)
2. `docs/specs/07_Vendor_Journey.md` or `docs/specs/08_Admin_Journey.md` (if relevant)
3. `CLAUDE.md` (architecture rules)
4. `docs/specs/03_Three_Product_Types.md` (if the flow touches services/bookings — type-aware)

## Output

### 1. High-level flow
Step-by-step journey, in user terms.

### 2. Technical mapping
For each step:
- Entry point (Controller / API route / Filament action)
- Action class invoked
- Models touched
- DB changes (inserts / updates / status transitions)
- Domain events fired (after commit)
- Notifications dispatched (channel × locale × template)

### 3. State transitions
List per entity (Booking, Service, BookingItem, …):
- Status field name
- Transition graph for this flow
- Whether the transition is type-aware (different per rental/sale/digital)

### 4. Edge cases
- Failures (validation, gateway, external API)
- Timeouts (vendor response deadline, payment hold expiry, reservation TTL)
- Rollbacks (refund flow per product type)
- Idempotency boundaries

### 5. Sequence diagram (text)