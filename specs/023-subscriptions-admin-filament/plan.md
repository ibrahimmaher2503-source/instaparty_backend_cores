---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md
---

# Implementation Plan: Subscriptions Admin Filament UI

**Branch**: `023-subscriptions-admin-filament` | **Date**: 2026-05-04 | **Spec**: [spec.md](./spec.md)
**Phase**: Phase 1.7 (ADR-0013 — Subscriptions Tiers Module, exit-criteria UI surface)

## Summary

Add the missing Filament admin surface for the Subscriptions module so administrators can manage subscription plans, monitor and override vendor tiers, browse invoices and payments, review the append-only audit log, and see subscription KPIs on the dashboard — all without any new migrations (tables and models are already shipped).

The work is pure Filament UI + two new Application Actions (`ApplyAdminTierOverrideAction`, `RevokeAdminTierOverrideAction`), two dashboard widgets, and one new `discoverResources` registration in `AdminPanelProvider`. No HTTP API endpoints, no new packages.

---

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12
**Primary Dependencies**: Filament v3, `filament/spatie-laravel-translatable-plugin`, `filament/spatie-laravel-media-library-plugin`, `bezhansalleh/filament-shield`, `spatie/laravel-model-states`
**Storage**: MySQL 8 — all 6 Subscriptions tables already exist (`subscription_plans`, `plan_features`, `vendor_subscriptions`, `subscription_invoices`, `subscription_payments`, `subscription_audit`)
**Testing**: Pest (Feature tests under `tests/Feature/Modules/Subscriptions/`)
**Target Platform**: `/admin` Filament panel
**Project Type**: Admin UI surface (Filament Resources + Widgets)
**Performance Goals**: Widget queries must resolve in < 200ms on typical dataset (index-backed aggregates)
**Constraints**: No new packages, no new migrations, no new DB tables; append-only discipline on `subscription_audit` and `subscription_payments`
**Scale/Scope**: 5 Filament Resources, 2 Actions, 2 Widgets, 4 Pest test groups

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | Applies? | Status | Notes |
|---|---|---|---|
| I. Modular Monolith | ✅ | **PASS** | All new files in `app/Modules/Subscriptions/`. No cross-module model imports — `VendorSubscription` and friends live in Subscriptions module and are only accessed from within it. |
| II. Three Product Types | ❌ | **N/A** | Subscriptions are vendor-level, not product-type–aware. No per-type variants needed. |
| III. Money Discipline | ✅ | **PASS** | Invoice amounts displayed via `->money('EGP', divideBy: 100)`. `SubscriptionPlan` prices use `MoneyCast` already. No float columns introduced. |
| IV. Bilingual EN+AR | ✅ | **PASS** | `SubscriptionPlan.name` and `PlanFeature.label` are already `$translatable`. Override reason field uses two separate inputs (EN + AR). Navigation group key added to both `en/` and `ar/` lang files. |
| V. Append-Only Tables | ✅ | **PASS** | `SubscriptionAuditEntryResource` and `SubscriptionPaymentResource` register no Create / Edit / Delete actions. Tests assert this. |
| VI. ADR Before Code | ✅ | **PASS** | ADR-0013 already accepted. No new module; no new ADR required. |
| VII. Test-First Critical Paths | ✅ | **PASS** | Override action, invoice filter, audit immutability, and widget count tests written same day per daily discipline rule. |
| VIII. Idempotency | ❌ | **N/A** | This feature exposes no HTTP API endpoints. Filament actions are admin-only and not subject to external idempotency requirements. |
| IX. Domain Events `DB::afterCommit` | ✅ | **PASS** | `ApplyAdminTierOverrideAction` fires `AdminOverrideApplied` via `DB::afterCommit()`. `RevokeAdminTierOverrideAction` fires `AdminOverrideEnded` the same way. |
| X. Vendor Approval Two-Step Gate | ❌ | **N/A** | Admin override doesn't require the vendor to be approved first (it's an admin-initiated action). |
| XI. Document Storage | ❌ | **N/A** | No file uploads in this feature. |

**Constitution verdict: PASS. Proceed to Phase 0.**

---

## Project Structure

### Documentation (this feature)

```text
specs/023-subscriptions-admin-filament/
├── plan.md              ← this file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks — not created here)
```

### Source Code (repository root)

```text
app/Modules/Subscriptions/
├── Application/
│   └── Actions/
│       ├── ApplyAdminTierOverrideAction.php     ← NEW
│       └── RevokeAdminTierOverrideAction.php    ← NEW
├── Filament/
│   ├── Resources/
│   │   ├── SubscriptionPlanResource.php         ← NEW (dir exists)
│   │   ├── SubscriptionPlanResource/
│   │   │   └── RelationManagers/
│   │   │       └── PlanFeaturesRelationManager.php  ← NEW
│   │   ├── VendorSubscriptionResource.php       ← NEW (dir exists)
│   │   ├── SubscriptionInvoiceResource.php      ← NEW
│   │   ├── SubscriptionInvoiceResource/
│   │   │   └── RelationManagers/
│   │   │       └── SubscriptionPaymentsRelationManager.php  ← NEW
│   │   ├── SubscriptionPaymentResource.php      ← NEW
│   │   └── SubscriptionAuditEntryResource.php   ← NEW
│   └── Widgets/
│       ├── VendorsByTierWidget.php              ← NEW
│       └── PastDueSubscriptionsStatWidget.php   ← NEW
└── Resources/
    └── lang/
        ├── en/subscription.php   ← MODIFY (add Filament labels)
        └── ar/subscription.php   ← MODIFY (add Filament labels)

app/Providers/Filament/
└── AdminPanelProvider.php        ← MODIFY (+discoverResources, +navGroup, +widgets)

tests/Feature/Modules/Subscriptions/
├── OverrideVendorTierActionTest.php    ← NEW
├── SubscriptionInvoiceResourceTest.php ← NEW
├── SubscriptionAuditImmutabilityTest.php ← NEW
└── SubscriptionWidgetTest.php           ← NEW
```

---

## Complexity Tracking

No constitution violations. No complexity justification required.

---

## Phase 0: Research

See `research.md` for full findings. Key decisions:

1. **Override action pattern**: Filament table `Action::make()` opens a modal form → calls `ApplyAdminTierOverrideAction::execute()`. No inline business logic in the closure.
2. **Append-only enforcement**: `SubscriptionAuditEntryResource` and `SubscriptionPaymentResource` override `getHeaderActions()` and `getTableActions()` to return `[]` — no CreateAction, EditAction, DeleteAction.
3. **FeatureResolver cache bust**: `SubscriptionPlanResource` uses `afterSave()` lifecycle hook to call `app(FeatureResolver::class)->forgetForPlan($record->id)`.
4. **Widget queries**: `VendorsByTierWidget` groups by `subscription_plan_id` on `vendor_subscriptions` filtered to `status IN (active, past_due)`. `PastDueSubscriptionsStatWidget` counts `status = past_due`. Both use DB aggregates, not Eloquent collection loops.
5. **VendorProfile display**: `VendorSubscription` has a `vendor_profile_id` FK. The vendor name is fetched via `->relationship('vendorProfile', 'business_name->en')` in the Filament column (raw JSON path query). The Identity module's `VendorProfile` model is accessed only via the relationship already on `VendorSubscription` — no direct cross-module import.

---

## Phase 1: Design & Contracts

See `data-model.md` for detailed entity/column reference. Highlights:

### New Application Actions

**`ApplyAdminTierOverrideAction`** (`Application/Actions/`)
```
execute(VendorProfile $vendor, SubscriptionPlan $plan, string $reasonEn, string $reasonAr, ?Carbon $expiresAt): VendorSubscription
```
Steps inside `DB::transaction`:
1. If an existing active override exists → set its status to `Superseded` and write audit entry (`subscription.superseded`).
2. Insert new `vendor_subscriptions` row: `is_admin_override=true`, `status=active`, `subscription_plan_id`, `vendor_profile_id`, `override_expires_at`.
3. Call `SubscriptionAuditWriter::write(event_type=admin_override_applied, reason={en}|{ar}, actor_type=admin, actor_id=auth()->id())`.
4. `DB::afterCommit(fn() => event(new AdminOverrideApplied($newSub)))`.

**`RevokeAdminTierOverrideAction`** (`Application/Actions/`)
```
execute(VendorSubscription $override): void
```
Steps inside `DB::transaction`:
1. Assert `$override->is_admin_override === true` — throw `InvalidArgumentException` if not.
2. Set `status = Cancelled`, `ended_at = now()`.
3. Call `SubscriptionAuditWriter::write(event_type=admin_override_ended, ...)`.
4. `DB::afterCommit(fn() => event(new AdminOverrideEnded($override)))`.

### Filament Resource Summary

| Resource | Model | Nav Group | Write Access |
|---|---|---|---|
| `SubscriptionPlanResource` | `SubscriptionPlan` | Subscriptions | Full CRUD |
| `VendorSubscriptionResource` | `VendorSubscription` | Subscriptions | View only + Override/Revoke actions |
| `SubscriptionInvoiceResource` | `SubscriptionInvoice` | Subscriptions | View only |
| `SubscriptionPaymentResource` | `SubscriptionPayment` | Subscriptions | View only |
| `SubscriptionAuditEntryResource` | `SubscriptionAuditEntry` | Subscriptions | View only (append-only) |

### Dashboard Widgets

| Widget | Type | Query |
|---|---|---|
| `VendorsByTierWidget` | `ChartWidget` (bar) | `vendor_subscriptions` grouped by `subscription_plan_id` where `status IN (active, past_due)`, joined to `subscription_plans.plan_code` |
| `PastDueSubscriptionsStatWidget` | `StatsOverviewWidget` | `vendor_subscriptions` count where `status = past_due` |

### AdminPanelProvider changes

Two additions:

```php
// ~line 52 (after existing discoverResources block):
->discoverResources(in: app_path('Modules/Subscriptions/Filament/Resources'), for: 'App\\Modules\\Subscriptions\\Filament\\Resources')

// In navigationGroups() array:
NavigationGroup::make('subscriptions')
    ->label(fn (): string => __('subscription.nav_group')),

// In widgets() or discoverWidgets():
->discoverWidgets(in: app_path('Modules/Subscriptions/Filament/Widgets'), for: 'App\\Modules\\Subscriptions\\Filament\\Widgets')
```

### Lang additions

`en/subscription.php` additions:
```php
'nav_group'         => 'Subscriptions',
'plans'             => 'Subscription Plans',
'vendor_subs'       => 'Vendor Subscriptions',
'invoices'          => 'Invoices',
'payments'          => 'Payments',
'audit'             => 'Audit Log',
'override_tier'     => 'Override Tier',
'revoke_override'   => 'Revoke Override',
'override_reason_en' => 'Override Reason (English)',
'override_reason_ar' => 'Override Reason (Arabic)',
'override_expires_at' => 'Override Expires At',
```

`ar/subscription.php` additions:
```php
'nav_group'         => 'الاشتراكات',
'plans'             => 'خطط الاشتراك',
'vendor_subs'       => 'اشتراكات الموردين',
'invoices'          => 'الفواتير',
'payments'          => 'المدفوعات',
'audit'             => 'سجل المراجعة',
'override_tier'     => 'تجاوز المستوى',
'revoke_override'   => 'إلغاء التجاوز',
'override_reason_en' => 'سبب التجاوز (بالإنجليزية)',
'override_reason_ar' => 'سبب التجاوز (بالعربية)',
'override_expires_at' => 'ينتهي التجاوز في',
```

---

## Cut-List (inherited from spec)

- Mass-import of plans via Excel → Phase 1.5
- Per-feature toggle UX in plan edit form → Phase 1.5
- "Compare plans" admin view → Phase 1.5
