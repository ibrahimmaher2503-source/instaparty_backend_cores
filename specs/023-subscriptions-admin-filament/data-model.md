# Data Model: Subscriptions Admin Filament UI

**Phase**: Phase 1 | **Date**: 2026-05-04

> All tables are already created by Phase 1.7 migrations. This document describes entities as they apply to the new Filament resources, the two new Actions, and the two dashboard widgets.

---

## Existing Entities (schema reference)

### `subscription_plans`
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT | Internal PK |
| `public_id` | CHAR(26) | ULID, exposed |
| `plan_code` | ENUM | `free\|silver\|gold\|premium` — cast to `PlanCode` enum |
| `name` | JSON | Translatable: `{"en":"...", "ar":"..."}` |
| `description` | JSON | Translatable |
| `billing_cycle_months` | TINYINT | 1 = monthly, 12 = yearly |
| `monthly_price_minor` | BIGINT | Cast via `MoneyCast` |
| `monthly_price_currency` | CHAR(3) | |
| `yearly_price_minor` | BIGINT | |
| `yearly_price_currency` | CHAR(3) | |
| `display_order` | TINYINT | For UI sort |
| `is_default` | BOOLEAN | Free tier default |
| `is_published` | BOOLEAN | Published plans visible to vendors |
| `deleted_at` | TIMESTAMP | SoftDeletes |

**Relationships**: `hasMany(PlanFeature)`, `hasMany(VendorSubscription)`

### `plan_features`
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT | PK |
| `subscription_plan_id` | FK → subscription_plans | |
| `feature_key` | VARCHAR(60) | e.g. `max_active_services`, `can_feature`, `commission_discount_bps` |
| `label` | JSON | Translatable human label |
| `value_type` | ENUM | `int\|bool\|string` |
| `value_int` | INT | Nullable |
| `value_bool` | BOOLEAN | Nullable |
| `value_string` | VARCHAR(255) | Nullable |

**Relationships**: `belongsTo(SubscriptionPlan)`

### `vendor_subscriptions`
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT | PK |
| `public_id` | CHAR(26) | ULID |
| `vendor_profile_id` | FK → vendor_profiles | Cross-module FK (display only) |
| `subscription_plan_id` | FK → subscription_plans | |
| `status` | VARCHAR(30) | State machine — `SubscriptionState` cast |
| `billing_cycle` | ENUM | Cast to `BillingCycle` |
| `current_period_start` | DATETIME | |
| `current_period_end` | DATETIME | |
| `grace_period_ends_at` | DATETIME | Nullable |
| `cancel_at_period_end` | BOOLEAN | |
| `is_admin_override` | BOOLEAN | True for admin-inserted rows |
| `override_expires_at` | DATETIME | Nullable — null = manual revoke only |
| `started_at` | DATETIME | |
| `ended_at` | DATETIME | Nullable |
| `paymob_subscription_token` | VARCHAR | Nullable |

**Relationships**: `belongsTo(SubscriptionPlan)`, `hasMany(SubscriptionInvoice)`, `hasMany(SubscriptionAuditEntry)`

### `subscription_invoices` (append-only, status-only UPDATE)
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT | PK |
| `public_id` | CHAR(26) | ULID |
| `vendor_subscription_id` | FK | |
| `vendor_profile_id` | FK | |
| `amount_minor` | BIGINT | Cast via `MoneyCast` |
| `amount_currency` | CHAR(3) | |
| `status` | ENUM | Cast to `InvoiceStatus`: `pending\|paid\|failed\|refunded` |
| `billing_cycle` | ENUM | |
| `period_start` | DATETIME | |
| `period_end` | DATETIME | |
| `due_date` | DATE | |
| `paid_at` | DATETIME | Nullable |
| `created_at` | TIMESTAMP | No `updated_at` |

**Relationships**: `belongsTo(VendorSubscription)`, `hasMany(SubscriptionPayment)`

### `subscription_payments` (insert-only)
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT | PK |
| `public_id` | CHAR(26) | ULID |
| `subscription_invoice_id` | FK | |
| `gateway_ref` | VARCHAR | Paymob transaction ref |
| `amount_minor` | BIGINT | |
| `amount_currency` | CHAR(3) | |
| `status` | VARCHAR | e.g. `success`, `failed` |
| `gateway_response` | JSON | Raw Paymob response |
| `created_at` | TIMESTAMP | No `updated_at` |

**Relationships**: `belongsTo(SubscriptionInvoice)`

### `subscription_audit` (append-only ledger)
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT | PK |
| `public_id` | CHAR(26) | ULID |
| `vendor_subscription_id` | FK | |
| `vendor_profile_id` | FK | |
| `event_type` | VARCHAR(60) | Cast to `SubscriptionEventType` enum |
| `actor_type` | VARCHAR(30) | `system` or `admin` |
| `actor_id` | BIGINT | Nullable — admin user ID |
| `before_state` | JSON | Nullable |
| `after_state` | JSON | Nullable |
| `metadata` | JSON | Nullable |
| `reason` | TEXT | Nullable — JSON-encoded `{"en":"...","ar":"..."}` for admin overrides |
| `created_at` | TIMESTAMP | No `updated_at` |

---

## New Application Actions

### `ApplyAdminTierOverrideAction`

**Location**: `app/Modules/Subscriptions/Application/Actions/ApplyAdminTierOverrideAction.php`

**Constructor dependencies**:
- `SubscriptionAuditWriter $auditWriter`

**Method signature**:
```php
public function execute(
    int $vendorProfileId,
    SubscriptionPlan $plan,
    string $reasonEn,
    string $reasonAr,
    ?\Carbon\Carbon $expiresAt = null,
): VendorSubscription
```

**Flow** (inside `DB::transaction`):
1. Query for existing active admin override on this vendor: `VendorSubscription::where('vendor_profile_id', $vendorProfileId)->adminOverride()->active()->first()`
2. If found → update its `status = 'superseded'`, `ended_at = now()`. Write audit entry `SubscriptionEventType::Superseded`.
3. Create new `VendorSubscription`: `is_admin_override=true`, `status=active`, `subscription_plan_id=$plan->id`, `vendor_profile_id=$vendorProfileId`, `override_expires_at=$expiresAt`, `started_at=now()`, `billing_cycle=manual`, `current_period_start=now()`, `current_period_end=$expiresAt ?? now()->addCentury()`.
4. `$this->auditWriter->write($newSub, SubscriptionEventType::AdminOverrideApplied, beforeState: [], afterState: ['plan_code' => $plan->plan_code->value], reason: json_encode(['en' => $reasonEn, 'ar' => $reasonAr]), actorType: 'admin', actorId: auth()->id())`
5. `DB::afterCommit(fn() => event(new AdminOverrideApplied($newSub)))`
6. Return `$newSub`

**Validation** (in Filament action form):
- `plan` — required, `Select` from active plans
- `reason_en` — required, `TextInput`, max 500
- `reason_ar` — required, `TextInput`, max 500, RTL
- `override_expires_at` — optional, `DateTimePicker`, min = tomorrow

---

### `RevokeAdminTierOverrideAction`

**Location**: `app/Modules/Subscriptions/Application/Actions/RevokeAdminTierOverrideAction.php`

**Constructor dependencies**:
- `SubscriptionAuditWriter $auditWriter`

**Method signature**:
```php
public function execute(VendorSubscription $override): void
```

**Precondition**: `$override->is_admin_override === true && !$override->status->isTerminal()`

**Flow** (inside `DB::transaction`):
1. Save before-state snapshot.
2. `$override->update(['status' => 'cancelled', 'ended_at' => now()])`
3. `$this->auditWriter->write($override, SubscriptionEventType::AdminOverrideEnded, beforeState: $beforeState, afterState: ['status' => 'cancelled'], actorType: 'admin', actorId: auth()->id())`
4. `DB::afterCommit(fn() => event(new AdminOverrideEnded($override)))`

---

## Filament Component Specifications

### `SubscriptionPlanResource`

**Table columns**:
- `plan_code` → badge with colors: Free=gray, Silver=info, Gold=warning, Premium=success
- `name` → translatable text (current locale)
- `monthly_price_minor` → `->money('EGP', divideBy: 100)`
- `display_order` → sortable
- `is_published` → `IconColumn::boolean()`
- `is_default` → `IconColumn::boolean()`

**Form fields**:
- `Tabs` with EN/AR tabs for `name`, `description`
- `Select::make('plan_code')` → `PlanCode::class` options
- `TextInput::make('monthly_price_minor')` + hidden currency
- `Toggle::make('is_published')`
- `Toggle::make('is_default')`
- `TextInput::make('display_order')` numeric

**After save** (in `handleRecordUpdate` + `handleRecordCreation`):
```php
app(FeatureResolver::class)->forgetForPlan($record->id);
```

**Relation manager**: `PlanFeaturesRelationManager`
- Table: feature_key, value_type, value_int/bool/string (conditional), label (translatable)
- Actions: Create, Edit, Delete

---

### `VendorSubscriptionResource`

**Table columns**:
- Vendor: `TextColumn::make('vendorProfile.business_name')` (JSON path resolves via Eloquent)
- Plan: `TextColumn::make('plan.plan_code')->badge()` with PlanCode color map
- Status: `TextColumn::make('status')->badge()` — active=success, past_due=warning, cancelled=danger, expired=danger, superseded=gray
- `is_admin_override` → `IconColumn::boolean()`
- `started_at` → dateTime
- `current_period_end` → dateTime (labelled "Expires At")
- `override_expires_at` → dateTime (nullable)

**Header actions**: none (no create on vendor subscriptions from admin)

**Table actions**:
- `Action::make('overrideTier')` — super-admin only, modal form calling `ApplyAdminTierOverrideAction`
- `Action::make('revokeOverride')` — visible only when `is_admin_override=true` and `!status->isTerminal()`, calls `RevokeAdminTierOverrideAction`
- `ViewAction::make()`

**Filters**:
- `SelectFilter::make('status')->options(SubscriptionStatus::class)`
- `TernaryFilter::make('is_admin_override')`
- `SelectFilter::make('plan')->relationship('plan', 'plan_code')`

---

### `SubscriptionInvoiceResource`

**Table columns**: vendor (via subscription), plan (via subscription.plan), amount_minor `->money('EGP', divideBy:100)`, status badge, period_start, period_end, due_date, paid_at

**Header actions**: none

**Table actions**: `ViewAction::make()` only

**Filters**: `SelectFilter::make('status')->options(InvoiceStatus::class)`, filter by vendor subscription

**View page**: Infolist with all columns + `RepeatableEntry` or embedded `SubscriptionPaymentsRelationManager`

---

### `SubscriptionPaymentResource`

**Table columns**: invoice (public_id link), amount `->money('EGP', divideBy:100)`, status, gateway_ref, created_at

**No create / edit / delete / header actions.** Only `ViewAction::make()`.

---

### `SubscriptionAuditEntryResource`

**Table columns**: actor (actor_type + actor_id label), event_type badge, vendor (via vendorProfile), created_at

**No create / edit / delete / header actions.** ViewAction only.

**Filters**: `SelectFilter::make('event_type')->options(SubscriptionEventType::class)`, filter by vendor_profile_id

---

### `VendorsByTierWidget` (ChartWidget)

```php
protected function getData(): array
{
    $counts = VendorSubscription::query()
        ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
        ->join('subscription_plans', 'vendor_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
        ->selectRaw('subscription_plans.plan_code, COUNT(*) as count')
        ->groupBy('subscription_plans.plan_code')
        ->pluck('count', 'plan_code');

    return [
        'datasets' => [[
            'label' => 'Vendors',
            'data'  => array_map(fn($code) => $counts->get($code, 0), ['free', 'silver', 'gold', 'premium']),
            'backgroundColor' => ['#9ca3af', '#60a5fa', '#fbbf24', '#34d399'],
        ]],
        'labels' => ['Free', 'Silver', 'Gold', 'Premium'],
    ];
}

protected function getType(): string { return 'bar'; }
```

---

### `PastDueSubscriptionsStatWidget` (StatsOverviewWidget)

```php
protected function getStats(): array
{
    $count = VendorSubscription::query()
        ->where('status', SubscriptionStatus::PastDue->value)
        ->count();

    return [
        Stat::make(__('subscription.past_due_stat'), $count)
            ->color($count > 0 ? 'warning' : 'success')
            ->icon('heroicon-o-exclamation-triangle'),
    ];
}
```

---

## State Validation Summary

| Entity | State transitions triggered by this feature |
|---|---|
| `VendorSubscription` (existing override) | `active → superseded` (when new override applied) |
| `VendorSubscription` (new override row) | `(new) → active` |
| `VendorSubscription` (revoked override) | `active → cancelled` |

---

## Tests: Expected Coverage

| Test file | What it covers |
|---|---|
| `OverrideVendorTierActionTest.php` | Override creates audit row; supersedes previous override; revoke sets status=cancelled |
| `SubscriptionInvoiceResourceTest.php` | List returns correct order; status filter returns correct subset; ViewAction exists; no Create/Edit/Delete |
| `SubscriptionAuditImmutabilityTest.php` | No Create / Edit / Delete actions visible for any role on audit resource and payment resource |
| `SubscriptionWidgetTest.php` | VendorsByTierWidget counts match seeded subscriptions; PastDueStat count matches seeded past_due rows |
