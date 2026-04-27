# Research: Phase 1 — Identity & Vendor Onboarding

**Date**: 2026-04-27
**Status**: Complete — no NEEDS CLARIFICATION markers remain

---

## R1 — Auth Mode: Sanctum Token vs SPA Cookie

**Decision**: Dual-mode. SPA cookie for Next.js web (stateful, `withCredentials`). Token for Flutter mobile (Bearer header).

**Rationale**: `02_Tech_Decisions.md` §3.1 mandates this split. Sanctum supports both via `EnsureFrontendRequestsAreStateful` middleware on web-only routes + `auth:sanctum` guard on all API routes.

**Implementation detail**:
- POST `/api/v1/login` → if request has `Origin` matching `SANCTUM_STATEFUL_DOMAINS`, returns Set-Cookie; otherwise returns `{ token: "..." }`
- Single `LoginAction` handles both cases by checking `$request->expectsJson()` vs stateful detection

**Alternatives considered**: Passport (too heavy for Phase 1), JWT (no stateful session needed for Next.js SPA — Sanctum SPA mode is cleaner).

---

## R2 — Phone OTP Strategy

**Decision**: Stub provider in Phase 1. `OtpService` interface in `Infrastructure/Gateways/`, `StubOtpGateway` implementation. Code `000000` always valid in non-production environments.

**Rationale**: Real SMS gateway wired in Phase 5 (Week 6). Stub avoids Phase 1 coupling to Vonage/local SMS before the vendor is selected. Test environments use the stub exclusively.

**Implementation detail**:
- `App\Modules\Identity\Domain\Contracts\OtpGateway` interface with `send(string $phone, string $code): void`
- `StubOtpGateway` — logs the OTP, returns immediately
- OTP stored in `users.phone_verification_code` (hashed) + `phone_verification_expires_at`; OR in Redis with TTL 10min (cleaner — no migration needed for OTP columns)
- Decision: **Redis** — `otp:{user_id}` key, 10-minute TTL, hashed code. No schema change.

**Alternatives considered**: Adding `otp_code` column to `users` — rejected (append-only OTP in Redis is cleaner, avoids migration + no PII in DB for short-lived tokens).

---

## R3 — Vendor Document Storage

**Decision**: Direct S3 storage via `Storage::disk('s3')->putFileAs(...)`. Path stored in `vendor_documents.file_path`. MediaLibrary NOT used for documents.

**Rationale**: `vendor_documents` has a review workflow (pending/approved/rejected status, `reviewed_by`, `review_notes`). MediaLibrary's strength is image galleries and conversions — it adds overhead without benefit for typed document management. The migration already has `file_path` + `file_name` columns.

**Alternatives considered**: MediaLibrary with custom collection — rejected (MediaLibrary's `media` table doesn't easily store review workflow metadata without extra columns or a custom model).

---

## R4 — Spatie Permission: Per-Type Vendor Permissions

**Decision**: Use Spatie's `givePermissionTo()` directly (not role-based) for per-type approval. The `vendor` role has NO service creation permissions by default. `ApproveVendorForTypeAction` grants explicit permissions to the user.

**Permission names** (registered in IdentityServiceProvider boot):
```
service.create.rental.own
service.update.rental.own
service.delete.rental.own
service.publish.rental.own
service.create.sale.own
service.update.sale.own
service.delete.sale.own
service.publish.sale.own
service.create.digital.own
service.update.digital.own
service.delete.digital.own
service.publish.digital.own
```

**Rationale**: Spatie `hasPermissionTo('service.create.rental.own')` is the enforcement point in Catalog (Phase 2). Granting/revoking permissions on the user directly (not via role) gives fine-grained per-type control.

**Alternatives considered**: Custom `vendor_approved_product_types` gate only — rejected (Spatie integration gives Filament Shield free permission UI generation).

---

## R5 — `filament/spatie-laravel-activitylog-plugin` vs `rmsramos/activitylog`

**Decision**: Use `rmsramos/activitylog` (already installed, has `config/filament-activitylog.php`). Do NOT swap for the official Filament plugin unless a concrete gap is found.

**Rationale**: Both packages provide an Activity Log resource in Filament. The official `filament/spatie-laravel-activitylog-plugin` version listed in `10_Package_List.md` is `^3.2` — but at time of Phase 1 build, `rmsramos/activitylog 2.0.0` is what's installed and configured. Swapping mid-phase introduces risk. `10_Package_List.md` should be updated to reflect `rmsramos/activitylog` as the installed equivalent.

**Action**: Update `10_Package_List.md` §3 to note `rmsramos/activitylog` as the installed Filament activitylog plugin (this is a docs-only change, no code change).

---

## R6 — Bilingual API Response Strategy (EN/AR)

**Decision**: Implement `SetLocaleMiddleware` that reads `Accept-Language` header (default `ar`) and calls `app()->setLocale()`. API Resources call `$model->getTranslation('field', app()->getLocale())` for single-locale mode.

**Rationale**: `04_Bilingual_Spec.md` §3.2 defines precedence order: query param → user preference → Accept-Language header → app default (ar).

**Phase 1 implementation**: Accept-Language header only (user locale preference stored but not yet applied — that's a profile update feature). `?translations=all` multi-locale mode for admin/vendor edit screens.

---

## R7 — Event Firing after DB Commit

**Decision**: All domain events use `DB::afterCommit(fn() => event(new XxxEvent(...)))` inside the transaction closure.

**Rationale**: CLAUDE.md §conventions rule 7. Prevents listeners from querying data before it's committed.

**Pattern used in all Actions**:
```php
return DB::transaction(function () use ($dto) {
    $user = $this->userRepository->create($dto);
    DB::afterCommit(fn() => event(new CustomerRegistered($user)));
    return $user;
});
```
