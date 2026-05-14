# Research: Subscriptions Admin Filament UI

**Phase**: Phase 0 | **Date**: 2026-05-04

## Decision 1: Override Action Delegation Pattern

**Decision**: Filament `Action::make('overrideTier')` opens a modal, collects plan + reason_en + reason_ar + optional expires_at, then calls `app(ApplyAdminTierOverrideAction::class)->execute(...)`. No business logic in the Filament closure.

**Rationale**: CLAUDE.md rule §8 — "Custom Actions delegate to Application Actions; never put business logic in the Filament `->action()` closure." The `SubscriptionAuditWriter::write()` call, the override row insertion, and the event dispatch all live inside the Action class.

**Alternatives considered**:
- Business logic in Filament closure: violates CLAUDE.md — rejected.
- Calling `SubscriptionLifecycleService` directly from Filament: that service has no `applyAdminOverride()` method; introducing one would mix lifecycle concerns — rejected in favor of a focused new Action.

---

## Decision 2: Append-Only Resource Enforcement

**Decision**: `SubscriptionAuditEntryResource` and `SubscriptionPaymentResource` enforce read-only by returning empty arrays from `getHeaderActions()` and `getTableActions()`, and declaring no `CreatePage`. Shield `generate --all` will create permissions, but no role will be granted create/edit/delete on these resources.

**Rationale**: Constitution Principle V — append-only tables (`subscription_audit`, `subscription_payments`) must not allow updates or deletes from any surface including Filament admin.

**Alternatives considered**:
- Using `canCreate() => false` etc.: works but still registers route stubs. Removing the page classes is cleaner and more defensive — chosen.

---

## Decision 3: VendorProfile Name Display (Cross-Module Access Pattern)

**Decision**: `VendorSubscription` already has a `vendor_profile_id` FK. In the Filament table column, use `TextColumn::make('vendorProfile.business_name')` which resolves via Eloquent's existing `belongsTo` on `VendorSubscription`. The `VendorProfile` model import in `VendorSubscription` was already in place before this feature — we don't add any new cross-module import.

**Rationale**: The `VendorSubscription` model in the Subscriptions module already has a `->belongsTo(VendorProfile::class)` relationship (cross-module, but established in Phase 1.7 backend). This feature doesn't introduce any new cross-module model references. The relationship accessor is the approved pattern for display in Filament.

**Alternative considered**: Adding a vendor display name snapshot column to `vendor_subscriptions` — overly complex for a display-only concern; rejected.

---

## Decision 4: Widget Query Strategy

**Decision**: `VendorsByTierWidget` runs a raw DB aggregate:
```php
VendorSubscription::query()
    ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
    ->join('subscription_plans', 'vendor_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
    ->selectRaw('subscription_plans.plan_code, COUNT(*) as count')
    ->groupBy('subscription_plans.plan_code')
    ->pluck('count', 'plan_code')
```

`PastDueSubscriptionsStatWidget` uses:
```php
VendorSubscription::query()->where('status', SubscriptionStatus::PastDue->value)->count()
```

**Rationale**: Both are single-query aggregates backed by the existing index on `(status)`. No Eloquent collection hydration needed. The chart widget caches via Filament's built-in `$pollingInterval`.

**Alternative considered**: Hydrating full models and counting in PHP — unnecessary memory overhead rejected.

---

## Decision 5: FeatureResolver Cache Invalidation Hook

**Decision**: Add `afterSave()` to `SubscriptionPlanResource`'s form lifecycle. In `handleRecordUpdate()`, after the parent `save()` call, call `app(FeatureResolver::class)->forgetForPlan($record->id)`. Identical hook in `PlanFeaturesRelationManager` after save.

**Rationale**: ADR-0013 specifies "Filament plan edits call `FeatureResolver::forgetForPlan()`". The `FeatureResolver::forgetForPlan(int $planId)` method already exists in `Application/Services/FeatureResolver.php`.

**Alternative considered**: Cache TTL expiry (1 hour) alone — unacceptable for admin UX where changes should be visible instantly.

---

## Decision 6: Shield Permission Naming

**Decision**: After `shield:generate --all`, the generated permission slugs will be:
- `view_any_subscription_plan`, `create_subscription_plan`, `update_subscription_plan`, `delete_subscription_plan`
- `view_any_vendor_subscription`, `override_vendor_subscription`
- `view_any_subscription_invoice`
- `view_any_subscription_payment`
- `view_any_subscription_audit_entry`

The `override_vendor_subscription` permission must be assigned only to `super_admin` role. All other view permissions can be assigned to `admin` and `support_staff` roles.

**Rationale**: ADR-0013 FR-020 and spec FR-EXT-006 — override is super-admin only.

---

## Resolved Unknowns

| Unknown | Resolved as |
|---|---|
| Does `SubscriptionAuditEntry` have a `vendor_profile_id` column directly? | Yes — `SubscriptionAuditWriter` writes `vendor_profile_id` from `$subscription->vendor_profile_id`. |
| Does the `reason` column in `subscription_audit` support EN/AR? | Confirmed: `reason` is a plain `text` column (migration 2026_05_03_000006). Store bilingual content as JSON string: `json_encode(['en' => $reasonEn, 'ar' => $reasonAr])`. The Filament form presents two TextInput fields; `ApplyAdminTierOverrideAction` serialises them before passing to `SubscriptionAuditWriter::write()`. |
| Is `VendorSubscription->vendor_profile_id` a direct column? | Yes, confirmed from model constructor. |
| Does SubscriptionPlan have `is_published` for filtering? | Yes — `scopePublished()` exists on the model. |
