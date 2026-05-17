# Data Model — Replacement Vendor Guard

**Feature**: `032-replacement-vendor-guard`
**Date**: 2026-05-16

## Summary

**Zero new tables. Zero new columns. Zero new ENUM values.** This feature is pure code hardening + one new value in an existing free-text column.

---

## Entities touched

### `audit_logs` (existing, append-only)

Reference: `docs/specs/11_DB_Schema.md` §Cross-cutting tables. Polymorphic, append-only.

| Column | Type | Used by this feature |
|---|---|---|
| `id` | BIGINT PK | auto |
| `public_id` | CHAR(26) ULID | new row gets a fresh ULID |
| `user_id` | FK→users NULL | the attempting user (may be NULL for unauthenticated requests) |
| `auditable_type` | string | `App\Modules\Booking\Domain\Models\Booking` |
| `auditable_id` | BIGINT | `$booking->id` |
| `action` | string | **NEW VALUE**: `booking.replacement_vendor_assignment_blocked` |
| `changes` | JSON | `{ attempted_at, role[], source, ip, user_agent }` |
| `created_at` | timestamp | `now()` |

**Mutability**: append-only (Constitution §V). No updates, no deletes — matches existing rules.

**Indexes**: existing indexes on `(auditable_type, auditable_id)` and `(user_id, created_at)` cover all query patterns this feature needs.

---

### `BookingPolicy` (Domain Policy class — code-only entity)

File: `app/Modules/Booking/Domain/Policies/BookingPolicy.php`

**Existing public surface** (unchanged):
- `viewAny(User): bool`
- `view(User, Booking): bool`
- `create(User): bool`
- `update(User, Booking): bool`
- `delete(User, Booking): bool`
- `deleteAny(User): bool`
- `forceDelete(User, Booking): bool`
- `forceDeleteAny(User): bool`
- `restore(User, Booking): bool`
- `restoreAny(User): bool`
- `replicate(User, Booking): bool`
- `reorder(User): bool`

**New public surface**:
- `assignReplacementVendor(User $user, Booking $booking): false` — always returns `false`, side-effect: writes one `audit_logs` row before returning.

**Invariants**:
1. Return type is the literal `false` (PHP 8.3 `false` return type) — encoded in the signature to make accidental `return true` a fatal compile-time / static-analysis error.
2. No early-return path skips the audit-log insert except when `audit_logs` is unreachable (e.g., test environments without DB) — in that case the insert is wrapped in `try/catch` and swallowed, but the refusal is unchanged.

---

## Audit-log action catalogue update

Add to `.specify/memory/api-registry.md` (or wherever audit actions are tracked):

| Action key | When fired | Auditable type | Notes |
|---|---|---|---|
| `booking.replacement_vendor_assignment_blocked` | Whenever `assignReplacementVendor` policy is invoked at runtime | `Booking` | Tripwire — should never fire in production. |

---

## Out of scope (data-model-wise)

- No new permission rows.
- No changes to `booking_admin_interventions` schema.
- No changes to `InterventionType` enum.
- No changes to `booking_vendors`.
- No changes to `bookings` table.
