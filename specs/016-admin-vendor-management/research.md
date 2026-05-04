# Research: Phase 6.4 — Admin Vendor Management

**Status**: Complete — all unknowns resolved
**Date**: 2026-05-03

---

## R-01: Audit trail — activity_log vs audit_logs

**Decision**: Use `activity()` helper (spatie/laravel-activitylog → `activity_log` table) for profile/hours/coverage/document edits. Write to BOTH `activity_log` AND the custom `audit_logs` table for impersonation events only.

**Rationale**: Existing actions (`UpdateVendorProfileAction`, `RevokeVendorTypeAction`) already use `activity()`. Adding a second write to `audit_logs` for the same events would duplicate data. Impersonation is a security-critical event that must appear in the admin-facing audit report (Phase 6.0 `audit_logs`), so it gets both.

**Impact on `ImpersonateVendorAction`**:
```php
// Inside DB::transaction:
activity()->on($vendorProfile)->causedBy($admin)->withProperties([...])->log('vendor_impersonated');
AuditLog::create([
    'auditable_type' => VendorProfile::class,
    'auditable_id'   => $vendorProfile->id,
    'user_id'        => $admin->id,
    'action'         => 'vendor_impersonated',
    'changes'        => json_encode(['token_ability' => 'impersonation', 'expires_minutes' => 30]),
    'ip_address'     => request()->ip(),
    'user_agent'     => request()->userAgent(),
]);
// Token creation after commit:
DB::afterCommit(fn() => $this->token = $vendorProfile->user->createToken('admin-impersonation', ['impersonation'], now()->addMinutes(30))->plainTextToken);
```

---

## R-02: Filament v3 tabbed view page — RelationManagers

**Decision**: Use `RelationManager` classes attached to `ViewVendorProfile` page via `getRelationManagers()`.

**Pattern**:
```php
// ViewVendorProfile.php
class ViewVendorProfile extends ViewRecord
{
    protected static string $resource = VendorProfileResource::class;

    public function getRelationManagers(): array
    {
        return [
            ServicesRelationManager::class,
            BookingVendorsRelationManager::class,
            WalletRelationManager::class,
            WithdrawalsRelationManager::class,
            VendorReviewsRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }
}
```

Each RelationManager defines a read-only `table()`. Filament renders each as a tab automatically when they are returned by `getRelationManagers()`.

**Cross-module data access**: RelationManagers query via DB facades or via relationship methods on `VendorProfile`. They do NOT import Eloquent models from Booking, Settlement, or Reviews modules. Use `DB::table('services')->where(...)` or define lightweight scope relationships on `VendorProfile`.

---

## R-03: Impersonation with Sanctum

**Decision**: Create a short-lived Sanctum token (30 minutes, `['impersonation']` ability) for the vendor user. Display in admin Notification for copy. Do not auto-redirect.

**Token creation** (after DB commit to ensure audit log is written first):
```php
$token = $vendorProfile->user->createToken(
    'admin-impersonation-' . now()->timestamp,
    ['impersonation'],
    now()->addMinutes(30)
)->plainTextToken;
```

**Filament action result**: Token returned as string from `ImpersonateVendorAction::execute()`. The Filament action closure displays it via a persistent info Notification.

**Permission**: `impersonate_vendor` — Spatie Permission, Shield-generated. Assign only to `super_admin` role in seeder.

---

## R-04: Business hours and coverage area edit

**Decision**: Business hours → reuse `UpsertVendorBusinessHoursAction` (already does full replace). Coverage areas → new `AdminReplaceVendorCoverageAction` (full delete-reinsert).

**Hours Filament pattern**: `Repeater` component in the edit form with 7 fixed rows (Sun–Sat) using `DayOfWeek` enum labels.

**Coverage Filament pattern**: `CheckboxList` of all cities (grouped by governorate) from the Geography module — queried via `DB::table('cities')->join('governorates', ...)`.

---

## R-05: Document re-upload

**Decision**: In the Documents tab (RelationManager), add an inline `Action` per row that opens a `FileUpload` modal. On submit, it calls `UploadVendorDocumentAction` with the existing record's `doc_type`, which updates `file_path` and `file_name` in place.

**S3 old file handling**: The old file path is stored before overwrite. After commit, `Storage::disk('s3')->delete($oldPath)` is called in a `DB::afterCommit` callback.

---

## Resolution Summary

| Unknown | Decision | Confidence |
|---------|----------|------------|
| Audit trail target | `activity_log` for edits; both tables for impersonation | High |
| Filament tabbed view pattern | RelationManagers on ViewRecord | High |
| Impersonation mechanism | Sanctum token, 30-min expiry | High |
| Business hours edit | Reuse `UpsertVendorBusinessHoursAction` | High |
| Coverage area edit | New `AdminReplaceVendorCoverageAction` | High |
| Document re-upload | Inline action → `UploadVendorDocumentAction` | High |

**All NEEDS CLARIFICATION markers resolved. Ready for implementation.**
