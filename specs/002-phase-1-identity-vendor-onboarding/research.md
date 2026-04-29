# Research: Identity & Vendor Onboarding (Phases 0.2 + 1.0 + 1.1)

**Date**: 2026-04-27
**Updated**: 2026-04-28
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


---

## R8 — MoneyCast Implementation Contract (Phase 0.2)

**Decision**: Custom `CastsAttributes` class in `app/Modules/Shared/Domain/Casts/MoneyCast.php`. Constructor receives a comma-separated column pair: `MoneyCast::class . ':delivery_fee_minor,delivery_fee_currency'`.

**Rationale**: Laravel's built-in `AsValueObject` doesn't support paired columns. `Brick\Money` doesn't need Eloquent coupling — the cast bridges the two cleanly. Keeping it in `Shared/Domain/Casts/` makes it importable by any module without cross-module model imports.

**Interface**:
```php
class MoneyCast implements CastsAttributes
{
    public function __construct(
        protected string $minorField,
        protected string $currencyField,
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($attributes[$this->minorField] === null) return null;
        return Money::ofMinor($attributes[$this->minorField], $attributes[$this->currencyField]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) return [$this->minorField => null, $this->currencyField => null];
        return [
            $this->minorField   => $value->getMinorAmount()->toInt(),
            $this->currencyField => $value->getCurrency()->getCurrencyCode(),
        ];
    }
}
```

**Alternatives considered**: `spatie/laravel-money` — rejected (not on `10_Package_List.md`; adds a package when a 20-line cast achieves the same result).

---

## R9 — Identity Migration Dependency Order (Phase 0.2)

**Decision**: Migrations run in this exact order to satisfy FK constraints:

1. `users` — Laravel framework default (already exists)
2. `vendor_profiles` — FK to `users`, `governorates`, `cities`
3. `vendor_documents` — FK to `vendor_profiles`, `users` (reviewed_by)
4. `vendor_approved_product_types` — FK to `vendor_profiles`, `users`
5. `vendor_business_hours` — FK to `vendor_profiles`
6. `vendor_coverage_areas` — FK to `vendor_profiles`, `cities` (owned by Geography migration 000005 — must already exist)
7. `customer_profiles` — FK to `users`
8. `customer_addresses` — FK to `users`, `cities`
9. `user_devices` — FK to `users`
10. `two_factor_secrets` — FK to `users`

**Cross-module FK note**: `vendor_profiles.primary_city_id` and `vendor_coverage_areas.city_id` and `customer_addresses.city_id` all FK to `cities` — Geography module migrations must run before Identity. `IdentityServiceProvider` uses `loadMigrationsFrom()` which respects alphabetical timestamp order within the module, not across modules. The `DatabaseSeeder` must call `Geography` migrations first via `migrate:fresh` ordering.

**Alternatives considered**: Deferring FK constraints — rejected (CLAUDE.md §conventions rule 13 mandates declared FK constraints).
