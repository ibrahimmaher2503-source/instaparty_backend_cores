---
description: Generate a Laravel Action for a specific use-case
argument-hint: <action-name> (e.g. CreateBooking, ApproveVendor)
---

# Generate Action

You're about to generate a Laravel Action for: **$ARGUMENTS**

## Required reading first

1. `CLAUDE.md` (architecture rules — thin controllers, fat Actions, transactions, events after commit)
2. `.claude/rules/actions.md` (Action class shape and conventions)
3. `.claude/rules/modules.md` (where the Action lives in the module structure)
4. The relevant journey doc:
   - Customer flows → `docs/specs/06_Customer_Journey.md`
   - Vendor flows → `docs/specs/07_Vendor_Journey.md`
   - Admin flows → `docs/specs/08_Admin_Journey.md`
5. If the Action is type-aware (touches services/bookings/imports/reviews), also read `docs/specs/03_Three_Product_Types.md`

## Plan first — output before writing any code

- **Action name + module location** (e.g. `app/Modules/Booking/Application/Actions/SubmitBookingAction.php`)
- **Is this type-aware?** If yes, you need three Actions (one per type) or `match($enum)` inside one Action — state which.
- **Input DTO / FormRequest structure**
- **Validation rules** (per-locale where applicable)
- **Business steps** numbered, in execution order
- **Models involved** and their relationships
- **Events / Notifications triggered** (fire AFTER commit)
- **DB transactions needed** (single? nested? distributed across modules via outbox?)
- **Idempotency**: does this need an `Idempotency-Key`?
- **Edge cases**: failures, rollbacks, timeouts, concurrent execution
- **Tests**: list the Pest cases needed (happy path + auth + authz + validation + idempotency + locale + all 3 types if applicable)

**Wait for Ibrahim's confirmation before writing code.**

## After confirmation

Generate:
- Action class in `app/Modules/<Module>/Application/Actions/`
- FormRequest in `app/Modules/<Module>/Http/Requests/` (if HTTP-driven)
- DTO in `app/Modules/<Module>/Application/DTOs/` (if cross-layer data carrier needed)
- Events in `app/Modules/<Module>/Domain/Events/`
- Pest tests in `tests/Feature/Modules/<Module>/`

Ensure:
- 3-line max action body in any controller invoking this
- `DB::transaction(...)` wraps mutations
- `DB::afterCommit(...)` for event firing
- No business logic leaks into Models
- No `if/elseif` chains on product type strings — use `match($enum)`
- Brick\Money for money values, never floats