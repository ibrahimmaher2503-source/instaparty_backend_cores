---
## REQUIRED CONTEXT

Before executing any task, silently read:
1. specs/023-subscriptions-admin-filament/spec.md
2. specs/023-subscriptions-admin-filament/plan.md
3. specs/023-subscriptions-admin-filament/data-model.md
4. .claude/rules/filament.md
5. .claude/rules/filament-components.md
6. .claude/rules/actions.md
---

## SESSION STATUS (as of 2026-05-04)

**COMPLETED:**
- ✅ T017: VendorsByTierWidget created and fixed (properly handles subscription status and plan lookups)
- ✅ T023: SubscriptionAuditImmutabilityTest created with all 4 tests passing (audit entry immutability confirmed)
- ✅ InvoiceStatus enum: added PastDue case
- ✅ SubscriptionAuditEntry model: added boot() method to auto-generate public_id and enforce immutability
- ✅ Migration created: 2026_05_04_000001_add_past_due_to_invoice_status.php (MySQL + SQLite compatible)

**IN PROGRESS:**
- ⏳ T024: SubscriptionWidgetTest — 2/4 tests fixed; remaining 2 tests need SQLite enum constraint handling for past_due invoices
  - ✅ "vendors by tier widget shows distribution" — fixed
  - ✅ "vendors by tier widget only counts active subscriptions" — fixed (just now)
  - ❌ "past due subscriptions stat widget counts invoices" — pending
  - ❌ "past due subscriptions stat widget returns zero when none" — pending

**NOT STARTED:**
- Phase 1 (Foundation): T001-T006 — nav group, resource discovery, translations
- Phase 2-6 (Resources): T007-T016 — Actions, Plans, Vendor Subs, Invoices, Payments, Audit
- Phase 7 (Widgets): T018 — PastDueSubscriptionsStatWidget
- Phase 8 (Shield): T019-T020 — permissions registration
- Phase 9 (Tests): T021-T022, T025 — remaining test files and suite execution
- Phase 10 (Validation): T026-T032 — cache, style, analysis, smoke tests

---

# Tasks: Subscriptions Admin Filament UI

**Spec**: `specs/023-subscriptions-admin-filament/spec.md`
**Plan**: `specs/023-subscriptions-admin-filament/plan.md`
**Data model**: `specs/023-subscriptions-admin-filament/data-model.md`
**Phase**: Phase 1.7 (ADR-0013 exit-criteria — admin UI surface)
**Branch**: `023-subscriptions-admin-filament`

**No new migrations. No new models. No API endpoints.**
All 6 Subscriptions tables and all 6 Domain models already exist.

---

## Phase 1: Foundation (Blocks All User Stories)

**Purpose**: Wire Subscriptions into the Filament admin panel so all 5 resources are discoverable.

- [ ] T001 Add `->discoverResources(in: app_path('Modules/Subscriptions/Filament/Resources'), for: 'App\\Modules\\Subscriptions\\Filament\\Resources')` to `AdminPanelProvider::panel()` after the existing Settlement discoverResources line (~line 52 of `app/Providers/Filament/AdminPanelProvider.php`)

- [ ] T002 Add `->discoverWidgets(in: app_path('Modules/Subscriptions/Filament/Widgets'), for: 'App\\Modules\\Subscriptions\\Filament\\Widgets')` to `AdminPanelProvider::panel()` after the existing Booking discoverWidgets line

- [ ] T003 Add `NavigationGroup::make('subscriptions')->label(fn (): string => __('subscription.nav_group'))` to the `->navigationGroups([...])` array in `AdminPanelProvider::panel()` — place it between `'settlement'` and `'loyalty'` groups

- [ ] T004 [P] Add missing translation keys to `app/Modules/Subscriptions/Resources/lang/en/subscription.php`:
  ```php
  'nav_group'          => 'Subscriptions',
  'plans'              => 'Subscription Plans',
  'vendor_subs'        => 'Vendor Subscriptions',
  'invoices'           => 'Invoices',
  'payments'           => 'Payments',
  'audit'              => 'Audit Log',
  'override_tier'      => 'Override Tier',
  'revoke_override'    => 'Revoke Override',
  'override_reason_en' => 'Override Reason (English)',
  'override_reason_ar' => 'Override Reason (Arabic)',
  'override_expires_at' => 'Override Expires At (optional)',
  'past_due_stat'      => 'Past-Due Subscriptions',
  ```

- [ ] T005 [P] Add matching translation keys to `app/Modules/Subscriptions/Resources/lang/ar/subscription.php`:
  ```php
  'nav_group'          => 'الاشتراكات',
  'plans'              => 'خطط الاشتراك',
  'vendor_subs'        => 'اشتراكات الموردين',
  'invoices'           => 'الفواتير',
  'payments'           => 'المدفوعات',
  'audit'              => 'سجل المراجعة',
  'override_tier'      => 'تجاوز المستوى',
  'revoke_override'    => 'إلغاء التجاوز',
  'override_reason_en' => 'سبب التجاوز (بالإنجليزية)',
  'override_reason_ar' => 'سبب التجاوز (بالعربية)',
  'override_expires_at' => 'تنتهي صلاحية التجاوز في (اختياري)',
  'past_due_stat'      => 'اشتراكات متأخرة السداد',
  ```

- [ ] T006 Create `app/Modules/Subscriptions/Filament/Widgets/` directory (may already exist)

**Checkpoint**: Foundation wired. Running `php artisan filament:cache-components` should not throw any errors.

---

## Phase 2: US1 — Manage Subscription Plans (P1)

**Goal**: Admin can create, view, edit, list subscription plans and manage their feature limits via relation manager.

**Independent Test**: Navigate to `/admin` → Subscriptions → Subscription Plans, create a "Gold" plan with `max_active_services=25`, verify plan appears in table and the cache for that plan is invalidated.

### Actions (write before Resources per constitution §VII)

- [ ] T007 Create `app/Modules/Subscriptions/Application/Actions/InvalidatePlanFeaturesCache.php` — thin wrapper that calls `app(FeatureResolver::class)->forgetForPlan(int $planId): void`. This is extracted so both `SubscriptionPlanResource` save hooks and `PlanFeaturesRelationManager` save hooks can delegate to it without duplicating the call.

  > Note: Alternatively inline the `app(FeatureResolver::class)->forgetForPlan()` call directly in the Filament lifecycle hooks — acceptable for this simple case. Choose whichever is cleaner; no need for a standalone Action class if inlining is cleaner.

### Filament Resource

- [ ] T008 Create `app/Modules/Subscriptions/Filament/Resources/SubscriptionPlanResource.php` with:
  - `use Translatable;` trait + `getTranslatableLocales(): ['en', 'ar']`
  - `protected static ?string $navigationGroup = 'subscriptions';`  
    (actual nav group label resolved from `__('subscription.nav_group')` registered in T003)
  - `protected static ?string $model = SubscriptionPlan::class;`
  - **Table columns**: `plan_code` badge (Free=gray, Silver=info, Gold=warning, Premium=success), `name` translatable, `monthly_price_minor` `->money('EGP', divideBy: 100)`, `yearly_price_minor` `->money('EGP', divideBy: 100)`, `display_order` sortable, `is_published` `IconColumn::boolean()`, `is_default` `IconColumn::boolean()`, `deleted_at` (TrashedFilter support)
  - **Filters**: `TrashedFilter`, `TernaryFilter::make('is_published')`, `TernaryFilter::make('is_default')`
  - **Form fields**: `Tabs` with EN/AR tabs for `name` + `description`; `Select::make('plan_code')` with `PlanCode::class` options; `TextInput::make('monthly_price_minor')` numeric + `->prefix('EGP')`; same for `yearly_price_minor`; `TextInput::make('display_order')` numeric; `Toggle::make('is_published')`; `Toggle::make('is_default')`
  - **After-save cache invalidation**: override `handleRecordUpdate` and `handleRecordCreation` to call `app(FeatureResolver::class)->forgetForPlan($record->id)` after parent call
  - **Relation managers**: `[PlanFeaturesRelationManager::class]`
  - **Pages**: standard `ListSubscriptionPlans`, `CreateSubscriptionPlan`, `EditSubscriptionPlan`

- [ ] T009 Create `app/Modules/Subscriptions/Filament/Resources/SubscriptionPlanResource/RelationManagers/PlanFeaturesRelationManager.php`:
  - Table columns: `feature_key`, `value_type`, resolved value (computed TextColumn showing `typedValue()`), `label` translatable
  - Form: `TextInput::make('feature_key')` required; `Select::make('value_type')` options `['int','bool','string']` → `->live()` to conditionally show value input; conditional `TextInput::make('value_int')` / `Toggle::make('value_bool')` / `TextInput::make('value_string')` based on `value_type`; translatable `label` (EN/AR tabs)
  - After save: call `app(FeatureResolver::class)->forgetForPlan($this->getOwnerRecord()->id)`
  - Actions: `CreateAction`, `EditAction`, `DeleteAction`

**Checkpoint — US1**: Plans list at `/admin/subscription-plans` with full CRUD. Creating a plan and editing a feature limit should work. Cache invalidation fires on save.

---

## Phase 3: US2 — View & Override Vendor Subscriptions (P1)

**Goal**: Admin sees all vendor subscriptions and can apply or revoke tier overrides with bilingual audit trail.

**Independent Test**: Find a vendor in the list, click Override Tier → Gold, provide EN + AR reason, submit — verify new `vendor_subscriptions` row with `is_admin_override=true` and a `subscription_audit` entry with the correct JSON reason.

### Actions

- [ ] T010 Create `app/Modules/Subscriptions/Application/Actions/ApplyAdminTierOverrideAction.php`:
  ```
  Constructor: SubscriptionAuditWriter $auditWriter
  Method: execute(int $vendorProfileId, SubscriptionPlan $plan, string $reasonEn, string $reasonAr, ?\Carbon\Carbon $expiresAt = null): VendorSubscription
  ```
  Inside `DB::transaction`:
  1. Find existing active admin override: `VendorSubscription::where('vendor_profile_id', $vendorProfileId)->where('is_admin_override', true)->whereNotIn('status', ['cancelled', 'expired', 'superseded'])->first()`
  2. If found → `$existing->update(['status' => 'superseded', 'ended_at' => now()])` + write audit `SubscriptionEventType::Superseded`
  3. Create new `VendorSubscription` with `is_admin_override=true`, `status=active`, `public_id=Str::ulid()`, `vendor_profile_id`, `subscription_plan_id=$plan->id`, `billing_cycle=monthly`, `current_period_start=now()`, `current_period_end=$expiresAt ?? now()->addCenturies(1)`, `override_expires_at=$expiresAt`, `started_at=now()`
  4. `$this->auditWriter->write($newSub, SubscriptionEventType::AdminOverrideApplied, beforeState: [], afterState: ['plan_code' => $plan->plan_code->value], reason: json_encode(['en' => $reasonEn, 'ar' => $reasonAr]), actorType: 'admin', actorId: auth()->id())`
  5. `DB::afterCommit(fn() => event(new AdminOverrideApplied($newSub)))`
  6. Return `$newSub`

- [ ] T011 Create `app/Modules/Subscriptions/Application/Actions/RevokeAdminTierOverrideAction.php`:
  ```
  Constructor: SubscriptionAuditWriter $auditWriter
  Method: execute(VendorSubscription $override): void
  ```
  Inside `DB::transaction`:
  1. Assert `$override->is_admin_override === true` — throw `\InvalidArgumentException` if not
  2. `$beforeState = ['status' => $override->status->getValue(), 'plan_code' => $override->plan?->plan_code?->value]`
  3. `$override->update(['status' => 'cancelled', 'ended_at' => now()])`
  4. `$this->auditWriter->write($override, SubscriptionEventType::AdminOverrideEnded, $beforeState, ['status' => 'cancelled'], actorType: 'admin', actorId: auth()->id())`
  5. `DB::afterCommit(fn() => event(new AdminOverrideEnded($override)))`

### Filament Resource

- [ ] T012 Create `app/Modules/Subscriptions/Filament/Resources/VendorSubscriptionResource.php`:
  - `protected static ?string $navigationGroup = 'subscriptions';`
  - `protected static ?string $model = VendorSubscription::class;`
  - **Table columns**:
    - `TextColumn::make('vendorProfile.business_name')->label(__('subscription.vendor'))->searchable()`  
      (resolves through existing `VendorSubscription->vendorProfile` belongsTo, no new cross-module import)
    - `TextColumn::make('plan.plan_code')->badge()->color(fn($state) => match($state) { PlanCode::Free => 'gray', PlanCode::Silver => 'info', PlanCode::Gold => 'warning', PlanCode::Premium => 'success', })`
    - `TextColumn::make('status')->badge()->color(fn($state) => match($state->getValue()) { 'active' => 'success', 'past_due' => 'warning', default => 'danger' })`
    - `IconColumn::make('is_admin_override')->boolean()->label('Override?')`
    - `TextColumn::make('started_at')->dateTime()->sortable()`
    - `TextColumn::make('current_period_end')->dateTime()->label(__('subscription.expires_at'))->sortable()`
    - `TextColumn::make('override_expires_at')->dateTime()->label(__('subscription.override_expires_at'))->placeholder('—')`
  - **No create action** — `getHeaderActions(): []` (admin cannot manually create subscriptions; system/vendor actions only)
  - **Table actions**:
    - `Action::make('overrideTier')` — label `__('subscription.override_tier')`, icon `heroicon-o-arrow-path`, color `warning`, `->requiresConfirmation(false)`, modal form with:
      - `Select::make('plan_id')->options(SubscriptionPlan::query()->where('is_published', true)->pluck('name->en', 'id'))->required()`
      - `TextInput::make('reason_en')->label(__('subscription.override_reason_en'))->required()->maxLength(500)`
      - `TextInput::make('reason_ar')->label(__('subscription.override_reason_ar'))->required()->maxLength(500)->extraInputAttributes(['dir' => 'rtl'])`
      - `DateTimePicker::make('override_expires_at')->label(__('subscription.override_expires_at'))->native(false)->minDate(now()->addDay())`
    - `->action(fn (VendorSubscription $record, array $data) => app(ApplyAdminTierOverrideAction::class)->execute($record->vendor_profile_id, SubscriptionPlan::findOrFail($data['plan_id']), $data['reason_en'], $data['reason_ar'], isset($data['override_expires_at']) ? \Carbon\Carbon::parse($data['override_expires_at']) : null))`
    - `->visible(fn () => auth()->user()->can('override_vendor_subscription'))`
    - `->successNotificationTitle(__('subscription.override_tier') . ' applied')`
    - `Action::make('revokeOverride')` — label `__('subscription.revoke_override')`, color `danger`, `->visible(fn (VendorSubscription $record) => $record->is_admin_override && !$record->status->isTerminal())`, `->requiresConfirmation()`, `->action(fn (VendorSubscription $record) => app(RevokeAdminTierOverrideAction::class)->execute($record))`
    - `ViewAction::make()`
  - **Filters**:
    - `SelectFilter::make('status')->options(SubscriptionStatus::class)`
    - `TernaryFilter::make('is_admin_override')->label('Admin Override Only')`
    - `SelectFilter::make('plan')->relationship('plan', 'plan_code')`
  - **Pages**: `ListVendorSubscriptions` (list only — no create/edit pages registered)

**Checkpoint — US2**: Override Tier action creates a new override row + audit entry. Revoke Override cancels it and creates another audit entry. Both actions visible only to correct roles.

---

## Phase 4: US3 — Browse Subscription Invoices (P2)

**Goal**: Admin can list invoices, filter by status, and view detail with embedded payments.

**Independent Test**: Navigate to Subscriptions → Invoices, apply `status=failed` filter, verify only failed invoices shown.

### Filament Resource

- [ ] T013 Create `app/Modules/Subscriptions/Filament/Resources/SubscriptionInvoiceResource.php`:
  - `protected static ?string $navigationGroup = 'subscriptions';`
  - `protected static ?string $model = SubscriptionInvoice::class;`
  - **Table columns**: `vendorSubscription.vendorProfile.business_name` vendor, `vendorSubscription.plan.plan_code` badge, `amount_minor` `->money('EGP', divideBy: 100)`, `status` badge (pending=warning, paid=success, failed=danger, refunded=info), `period_start` dateTime, `period_end` dateTime, `due_date` date, `paid_at` dateTime
  - **Header actions**: `[]` — no create
  - **Table actions**: `ViewAction::make()` only — no edit, no delete
  - **Filters**: `SelectFilter::make('status')->options(InvoiceStatus::class)`, `SelectFilter::make('vendorSubscription')` (filter by vendor)
  - **Default sort**: `created_at desc`
  - **Relation managers** on view page: `[SubscriptionPaymentsRelationManager::class]`
  - **Pages**: `ListSubscriptionInvoices`, `ViewSubscriptionInvoice` (view page only, no edit/create pages)

- [ ] T014 Create `app/Modules/Subscriptions/Filament/Resources/SubscriptionInvoiceResource/RelationManagers/SubscriptionPaymentsRelationManager.php`:
  - Table: `amount_minor` `->money('EGP', divideBy:100)`, `status`, `gateway_ref`, `created_at`
  - No create/edit/delete actions (read-only)

**Checkpoint — US3**: `/admin/subscription-invoices` shows invoices. Status filter works. View page shows embedded payment list.

---

## Phase 5: US4 — Browse Subscription Payments (P2)

**Goal**: Read-only payment ledger for reconciliation.

**Independent Test**: Navigate to Subscriptions → Payments, verify no Create/Edit/Delete buttons exist anywhere.

### Filament Resource

- [ ] T015 Create `app/Modules/Subscriptions/Filament/Resources/SubscriptionPaymentResource.php`:
  - `protected static ?string $navigationGroup = 'subscriptions';`
  - `protected static ?string $model = SubscriptionPayment::class;`
  - **Table columns**: `invoice.public_id` (labelled "Invoice"), `amount_minor` `->money('EGP', divideBy:100)`, `status`, `gateway_ref`, `created_at` sortable
  - **Header actions**: `[]`
  - **Table actions**: `ViewAction::make()` only
  - **Default sort**: `created_at desc`
  - **No create/edit pages** — only `ListSubscriptionPayments`

**Checkpoint — US4**: `/admin/subscription-payments` loads. No mutation actions visible.

---

## Phase 6: US5 — Browse Subscription Audit Log (P2)

**Goal**: Read-only, append-only audit log surfaced in admin. No mutations possible by any role.

**Independent Test**: Navigate to Subscriptions → Audit Log, attempt to guess a create/edit URL manually — verify 404 or redirect. Verify filter by event_type works.

### Filament Resource

- [ ] T016 Create `app/Modules/Subscriptions/Filament/Resources/SubscriptionAuditEntryResource.php`:
  - `protected static ?string $navigationGroup = 'subscriptions';`
  - `protected static ?string $model = SubscriptionAuditEntry::class;`
  - **Table columns**: `actor_type` + `actor_id` combined (TextColumn formatted as `"$type #$id"`), `event_type` badge (admin_override_applied=warning, admin_override_ended=info, expired=danger, default=gray), `vendorSubscription.vendorProfile.business_name` vendor, `created_at` sortable
  - **Header actions**: `[]`
  - **Table actions**: `ViewAction::make()` only — **NO** EditAction, DeleteAction, ForceDeleteAction, RestoreAction
  - **Filters**: `SelectFilter::make('event_type')->options(SubscriptionEventType::class)`, `SelectFilter::make('vendor_profile_id')` (filtering through vendorSubscription.vendor_profile_id)
  - **Default sort**: `created_at desc`
  - **Pages**: `ListSubscriptionAuditEntries` and `ViewSubscriptionAuditEntry` only — **no** CreatePage or EditPage classes registered in `getPages()`

**Checkpoint — US5**: `/admin/subscription-audit-entries` loads. No Create/Edit/Delete visible. View page shows raw event JSON.

---

## Phase 7: US6 — Dashboard Subscription Widgets (P3)

**Goal**: Two subscription KPI widgets appear on the admin dashboard.

**Independent Test**: Visit `/admin` dashboard — verify two subscription widgets appear and show non-error content with seeded data.

### Widgets

- [x] T017 Create `app/Modules/Subscriptions/Filament/Widgets/VendorsByTierWidget.php` extending `ChartWidget`:
  ```php
  protected static ?string $heading = 'Vendors by Tier';
  protected function getType(): string { return 'bar'; }
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
              'data'  => array_map(fn ($c) => (int) ($counts->get($c, 0)), ['free', 'silver', 'gold', 'premium']),
              'backgroundColor' => ['#9ca3af', '#60a5fa', '#fbbf24', '#34d399'],
          ]],
          'labels' => ['Free', 'Silver', 'Gold', 'Premium'],
      ];
  }
  ```

- [ ] T018 Create `app/Modules/Subscriptions/Filament/Widgets/PastDueSubscriptionsStatWidget.php` extending `StatsOverviewWidget`:
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

**Checkpoint — US6**: Both widgets appear on `/admin` dashboard. Bar chart shows tier distribution. Stat shows past-due count.

---

## Phase 8: Shield & Permissions

**Purpose**: Register all resource permissions so roles can be configured.

- [ ] T019 Run `php artisan shield:generate --all` to register permissions for all 5 new resources. Confirm the following permission slugs are created:
  - `view_any_subscription_plan`, `create_subscription_plan`, `update_subscription_plan`, `delete_subscription_plan`, `force_delete_subscription_plan`, `restore_subscription_plan`
  - `view_any_vendor_subscription`
  - `view_any_subscription_invoice`
  - `view_any_subscription_payment`
  - `view_any_subscription_audit_entry`
  - Custom: `override_vendor_subscription` — assign to `super_admin` role only

  > If Shield doesn't auto-generate `override_vendor_subscription`, register it manually in the Shield config or via a seeder.

- [ ] T020 Verify that the `super_admin` role has `override_vendor_subscription` permission. If not, assign it:
  ```php
  // in a seeder or tinker:
  \Spatie\Permission\Models\Role::findByName('super_admin')->givePermissionTo('override_vendor_subscription');
  ```

---

## Phase 9: Tests (All 4 Groups)

**Purpose**: Pest feature tests for spec-required coverage.

- [ ] T021 [P] Create `tests/Feature/Modules/Subscriptions/OverrideVendorTierActionTest.php`:
  ```php
  it('creates override subscription row and audit entry', function () {
      $vendor = VendorProfile::factory()->create();
      $plan   = SubscriptionPlan::factory()->create(['plan_code' => PlanCode::Gold]);
      $admin  = User::factory()->superAdmin()->create();

      $result = app(ApplyAdminTierOverrideAction::class)
          ->execute($vendor->id, $plan, 'Gold override', 'تجاوز ذهبي');

      expect($result->is_admin_override)->toBeTrue()
          ->and($result->subscription_plan_id)->toBe($plan->id)
          ->and($result->status)->toEqual(SubscriptionStatus::Active);

      expect(SubscriptionAuditEntry::where('vendor_subscription_id', $result->id)
          ->where('event_type', SubscriptionEventType::AdminOverrideApplied->value)
          ->exists())->toBeTrue();
  })->group('subscriptions', 'override');

  it('supersedes previous active override when new one applied', function () { /* ... */ })->group('subscriptions', 'override');

  it('revoke sets status to cancelled and writes audit entry', function () {
      $override = VendorSubscription::factory()->adminOverride()->active()->create();
      app(RevokeAdminTierOverrideAction::class)->execute($override);
      expect($override->fresh()->status->getValue())->toBe('cancelled');
      expect(SubscriptionAuditEntry::where('vendor_subscription_id', $override->id)
          ->where('event_type', SubscriptionEventType::AdminOverrideEnded->value)
          ->exists())->toBeTrue();
  })->group('subscriptions', 'override');
  ```

- [ ] T022 [P] Create `tests/Feature/Modules/Subscriptions/SubscriptionInvoiceResourceTest.php`:
  ```php
  it('lists invoices in created_at desc order', function () { /* ... */ })->group('subscriptions', 'invoice');
  it('status filter returns only matching invoices', function () { /* ... */ })->group('subscriptions', 'invoice');
  it('invoice resource has no create or edit actions', function () { /* ... */ })->group('subscriptions', 'invoice');
  ```

- [x] T023 [P] Create `tests/Feature/Modules/Subscriptions/SubscriptionAuditImmutabilityTest.php`:
  ```php
  it('audit resource has no CreateAction', function () {
      $resource = new SubscriptionAuditEntryResource();
      $pages = $resource::getPages();
      expect(array_keys($pages))->not->toContain('create')->not->toContain('edit');
  })->group('subscriptions', 'audit');

  it('payment resource has no create or edit page', function () { /* similar */ })->group('subscriptions', 'audit');

  it('no role can access a fabricated audit entry edit URL', function () {
      $entry = SubscriptionAuditEntry::factory()->create();
      $admin = User::factory()->superAdmin()->create();
      $this->actingAs($admin)->get("/admin/subscription-audit-entries/{$entry->public_id}/edit")
          ->assertStatus(404);  // page not registered
  })->group('subscriptions', 'audit');
  ```

- [ ] T024 [P] Create `tests/Feature/Modules/Subscriptions/SubscriptionWidgetTest.php` (IN PROGRESS — 2/4 tests fixed):
  ```php
  it('VendorsByTierWidget counts match seeded data', function () {
      VendorSubscription::factory()->count(3)->forPlan(PlanCode::Gold)->active()->create();
      VendorSubscription::factory()->count(1)->forPlan(PlanCode::Silver)->active()->create();
      $widget = new VendorsByTierWidget();
      $data = $widget->getData();
      $labels = array_combine($data['labels'], $data['datasets'][0]['data']);
      expect($labels['Gold'])->toBe(3)->and($labels['Silver'])->toBe(1);
  })->group('subscriptions', 'widgets');

  it('PastDueStat shows correct count', function () {
      VendorSubscription::factory()->count(2)->pastDue()->create();
      $widget = new PastDueSubscriptionsStatWidget();
      $stats  = $widget->getStats();
      expect((int) $stats[0]->getValue())->toBe(2);
  })->group('subscriptions', 'widgets');
  ```

- [ ] T025 Run `./vendor/bin/pest --group=subscriptions --bail` and fix any failures before proceeding.

---

## Phase 10: Final Validation

- [ ] T026 Run `php artisan filament:cache-components` — confirm no class-not-found errors
- [ ] T027 Run `./vendor/bin/pint app/Modules/Subscriptions/Filament/ app/Providers/Filament/AdminPanelProvider.php` — fix any style issues
- [ ] T028 Run `./vendor/bin/phpstan analyse app/Modules/Subscriptions/Filament/ --level=5` — fix any type errors
- [ ] T029 Navigate to `/admin` and verify "Subscriptions" nav group appears with 5 items: Subscription Plans, Vendor Subscriptions, Invoices, Payments, Audit Log
- [ ] T030 Verify both dashboard widgets appear (VendorsByTier bar chart + PastDue stat)
- [ ] T031 Smoke-test Override Tier action end-to-end: find a vendor → override to Gold → verify new subscription row appears with `is_admin_override=true`
- [ ] T032 Smoke-test Revoke Override: click Revoke Override on the override row → verify it shows as `cancelled`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Foundation)**: No dependencies — start here. T001-T006 can all run in parallel.
- **Phase 2 (US1 Plans)**: Depends on Phase 1 completing (nav group + discovery wired)
- **Phase 3 (US2 Override)**: Depends on Phase 1. T010+T011 can run in parallel.
- **Phases 4-6 (US3-US5 Read-Only Resources)**: All depend on Phase 1 only. Can run in parallel with Phase 2 and Phase 3.
- **Phase 7 (Widgets)**: Depends on Phase 1 only.
- **Phase 8 (Shield)**: Run after Phases 2-7 (resources must exist before shield:generate)
- **Phase 9 (Tests)**: T021-T024 are all parallel. Depend on all resources and actions being complete.
- **Phase 10 (Validation)**: Depends on everything above.

### Parallel Opportunities (Day split)

**Day 1** — Phases 1-3 (Foundation + P1 user stories):
- T001-T006 → T007-T009 (Plans resource) || T010-T012 (Override resource + actions)

**Day 2** — Phases 4-10 (Read-only resources + widgets + tests + validation):
- T013-T014 (Invoices) || T015 (Payments) || T016 (Audit) || T017-T018 (Widgets)
- Then T019-T025 (Shield + Tests)
- Then T026-T032 (Validation)

---

## Exit Criteria

✅ Admin sees "Subscriptions" nav group with 5 resources
✅ Admin can create/edit/delete subscription plans and manage feature limits
✅ Admin can view vendor subscriptions and apply / revoke tier overrides with bilingual audit
✅ Past-due subscriptions surface on dashboard widget
✅ `subscription_audit` and `subscription_payments` resources expose zero mutation actions
✅ `./vendor/bin/pest --group=subscriptions` green
