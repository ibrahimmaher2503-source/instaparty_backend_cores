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

CONSTRAINTS (non-negotiable):
- Every generated artifact must cite specific FR numbers from 01_PRD.md
- Every generated artifact must cite specific table names from 11_DB_Schema.md
- Every generated artifact must align to a Phase ID from 09_Phasing_Plan.md
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack
---

# Tasks: Phase 6.4 â€” Admin Vendor Management (Full CRUD)

**Feature**: `specs/016-admin-vendor-management/`
**Branch**: `008-settlement-wallets-commissions-withdrawals`
**Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md) | **Data Model**: [data-model.md](./data-model.md)
**PRD coverage**: FR-16 (admin monitor/follow-up), FR-17 (admin facilitate without replacing), FR-28 (wallet visibility), FR-29 (withdrawal approval visibility)
**Phase**: 6.4 (1 day, Week 7) â€” no migrations required

**Organization**: Tasks grouped by user story from spec.md (P1â†’P5).

---

## Phase 1: Setup (Shared â€” no story label)

**Purpose**: Verify prerequisites and add new permission before touching any Filament files.

- [X] T001 Verify `vendor_approved_product_types.revoke_reason` JSON column exists in migration `app/Modules/Identity/Database/Migrations/2026_01_01_000012_create_vendor_approved_product_types_table.php` â€” if missing, create an additive migration `add_revoke_reason_to_vendor_approved_product_types_table.php` in the same migrations directory
- [X] T002 Add `impersonate_vendor` permission to Spatie Permission seeder in `database/seeders/` (or Identity module seeder) and assign it to `super_admin` role only
- [X] T003 Add `re_upload_vendor_document` permission to the same seeder, assigned to `admin` and `super_admin` roles
- [X] T004 Add all new lang keys to `app/Modules/Identity/Resources/lang/en/identity.php` â€” keys: `nav.vendor_management`, `actions.impersonate`, `actions.replace_coverage`, `actions.re_upload_document`, `sections.business_hours`, `sections.coverage_areas`, `sections.services`, `sections.bookings`, `sections.wallet`, `sections.withdrawals`, `sections.reviews`, `sections.activity`, `notifications.impersonation_token`, `notifications.coverage_updated`, `confirmations.impersonate_warning`
- [X] T005 Add matching Arabic translations for all keys from T004 to `app/Modules/Identity/Resources/lang/ar/identity.php`

---

## Phase 2: Foundational (blocking all Filament work)

**Purpose**: New action classes that the Filament UI will delegate to.

- [X] T006 Create `app/Modules/Identity/Application/Actions/ImpersonateVendorAction.php` â€” `execute(VendorProfile $vendorProfile, User $adminUser): string`. Inside `DB::transaction`: (1) write to `activity_log` via `activity()->on($vendorProfile)->causedBy($adminUser)->withProperties([...])->log('vendor_impersonated')`, (2) write a direct insert to `audit_logs` table with `auditable_type=VendorProfile::class`, `auditable_id=$vendorProfile->id`, `user_id=$adminUser->id`, `action='vendor_impersonated'`, `changes=json_encode(['token_ability'=>'impersonation','expires_minutes'=>30])`, `ip_address=request()->ip()`, `user_agent=request()->userAgent()`. After commit via `DB::afterCommit`: create Sanctum token `$vendorProfile->user->createToken('admin-impersonation-'.now()->timestamp, ['impersonation'], now()->addMinutes(30))->plainTextToken` stored in a class property. Return the plain-text token string.

- [X] T007 Create `app/Modules/Identity/Application/Actions/AdminReplaceVendorCoverageAction.php` â€” `execute(VendorProfile $vendorProfile, array $areas): void`. `$areas` shape: `[['city_id'=>int,'delivery_fee_minor'=>int,'delivery_fee_currency'=>string,'min_order_minor'=>int,'min_order_currency'=>string]]`. Inside `DB::transaction`: delete all `vendor_coverage_areas` rows for `$vendorProfile->id`, insert new rows. After transaction: `activity()->on($vendorProfile)->causedBy(auth()->user())->withProperties(['area_count'=>count($areas)])->log('coverage_areas_replaced')`.

- [X] T008 Review `app/Modules/Identity/Application/Actions/UpdateVendorProfileAction.php` â€” the existing action only activity-logs sensitive bank fields. Extend it to log ALL changed fields: before executing the save, snapshot `$vendorProfile->getDirty()` after `fill()` and include it in a broader `activity()->log('admin_updated_vendor_profile')` call. Ensure `approval_status` is explicitly stripped from `$data` in the action's `execute()` before the `fill()` call so it can never be changed via this action.

---

## Phase 3: User Story 1 â€” Edit Vendor Business Details (P1)

**Story goal**: Admin can edit all business fields with EN+AR translatable tabs; save preserves `approval_status`.

**Independent test**: Navigate to VendorResource â†’ open edit form â†’ change `business_name` (EN+AR) â†’ save â†’ verify `approval_status` unchanged.

- [X] T009 [US1] Add `form(Form $form): Form` static method to `app/Modules/Identity/Filament/Resources/VendorProfileResource.php`. Schema: `Tabs` with EN + AR tabs at top (using `filament/spatie-laravel-translatable-plugin` `Translatable` concern already on resource). Under tabs: `Section` "Business Profile" with `TextInput` for `business_name`, `Textarea` for `bio`, `Select` for `business_type`. `Section` "Location" with `Select` for `primary_governorate_id` (searchable, from `governorates`) cascading to `Select` for `primary_city_id` (filtered by governorate, `->reactive()`). `Section` "Registration" with `TextInput` for `commercial_register_no`, `tax_id`, `national_id`, `address_line`. `Section` "Banking" with `TextInput` for `bank_holder_name`, `bank_iban`, `bank_swift`, `bank_name` â€” all `->password()->revealable()` except `bank_holder_name`.

- [X] T010 [US1] Create `app/Modules/Identity/Filament/Resources/VendorProfileResource/Pages/EditVendorProfile.php` â€” standard `EditRecord` page. Override `handleRecordUpdate(Model $record, array $data): Model` to call `app(UpdateVendorProfileAction::class)->execute($record, $data)` instead of default Eloquent save. Also call `app(UpsertVendorBusinessHoursAction::class)->execute($record, $data['business_hours'] ?? [])` and `app(AdminReplaceVendorCoverageAction::class)->execute($record, $data['coverage_areas'] ?? [])` within the same override.

- [X] T011 [US1] Add business hours subsection to the form in `VendorProfileResource::form()` â€” `Section` "Business Hours" containing a `Repeater` named `business_hours` with: `Select` for `day_of_week` (options from `DayOfWeek` enum labels, `->distinct()`), `TimePicker` for `opens_at` (nullable â€” NULL = closed), `TimePicker` for `closes_at`. Add `->defaultItems(7)` pre-filled with Sunâ€“Sat rows on record load via `->fillStateUsing()`.

- [X] T012 [US1] Add coverage areas subsection to the form in `VendorProfileResource::form()` â€” `Section` "Coverage Areas" containing a `CheckboxList` named `coverage_city_ids` with options loaded from `DB::table('cities')->join('governorates','governorates.id','=','cities.governorate_id')->select('cities.id','cities.name','governorates.name as gov_name')->get()` grouped by governorate name. Add delivery fee and min order `TextInput` fields below (or inside a secondary `Repeater` if per-city pricing is needed â€” per data-model.md use Repeater with city select + fee inputs).

- [X] T013 [US1] Register the `EditVendorProfile` page in `VendorProfileResource::getPages()` â€” add `'edit' => Pages\EditVendorProfile::route('/{record}/edit')`. Also add `Tables\Actions\EditAction::make()` to the table `->actions([...])` array (before ViewAction).

- [X] T014 [US1] Add `approval_status` filter and `primary_governorate_id` filter to `VendorProfileResource::table()` `->filters([...])`. Add `SelectFilter::make('primary_governorate_id')->relationship('primaryGovernorate','name')->label(__('identity.columns.governorate'))->searchable()`. Add `Filter::make('has_type_approval')->form([Select::make('product_type')->options(ProductType::class)])->query(fn($query,$data) => $query->when($data['product_type'],fn($q,$t)=>$q->whereHas('approvedTypes',fn($q2)=>$q2->where('product_type',$t)->whereNull('revoked_at'))))`.

---

## Phase 4: User Story 2 â€” Force-Revoke Type Approval with Reason (P2)

**Story goal**: Admin revokes a product-type approval with a mandatory bilingual reason; reason is stored in `vendor_approved_product_types.revoke_reason`.

**Independent test**: Open revokeType action on an approved vendor â†’ submit EN+AR reason â†’ verify DB row has `revoke_reason = {en:...,ar:...}` and `revoked_at` is set.

- [ ] T015 [US2] Verify `revokeType` action in `VendorProfileResource::table()` already has `Textarea::make('revoke_reason_en')->required()` and `Textarea::make('revoke_reason_ar')->required()`. If either is not `->required()`, add that constraint now. This ensures reason is mandatory per FR-007 and spec SC-003.

- [ ] T016 [US2] Verify `RevokeVendorTypeAction::execute()` in `app/Modules/Identity/Application/Actions/RevokeVendorTypeAction.php` correctly writes `revoke_reason` as JSON `{en:...,ar:...}` to `vendor_approved_product_types` and fires `VendorTypeRevoked` event via `DB::afterCommit`. If the current implementation uses a plain string instead of JSON, update the update call to `['revoke_reason' => ['en' => $revokeReason['en'] ?? '', 'ar' => $revokeReason['ar'] ?? '']]`.

- [ ] T017 [P] [US2] Verify `VendorTypeRevoked` event in `app/Modules/Identity/Domain/Events/VendorTypeRevoked.php` is listened by a notification dispatcher listener in `app/Modules/Identity/Application/Listeners/` (or Communication module listener). If no listener exists for this event that dispatches a vendor notification, create `app/Modules/Identity/Application/Listeners/NotifyVendorOnTypeRevoked.php` that calls `DispatchNotificationAction` (Communication module) with `event_key = 'vendor_type_revoked'`. Register in Identity `ServiceProvider`.

---

## Phase 5: User Story 3 â€” Impersonate Vendor (Audit-Logged) (P3)

**Story goal**: Admin triggers impersonation; audit log is written BEFORE token is returned; unauthorized admin gets 403.

**Independent test**: Trigger impersonate action as `super_admin` â†’ assert `audit_logs` row with `action='vendor_impersonated'` exists â†’ assert token string is returned.

- [X] T018 [US3] Add `impersonate` table row `Action` to `VendorProfileResource::table()` `->actions([...])`:
  - `Action::make('impersonate')->label(__('identity.actions.impersonate'))->icon('heroicon-o-eye')->color('gray')`
  - `->requiresConfirmation()->modalDescription(__('identity.confirmations.impersonate_warning'))`
  - `->visible(fn(VendorProfile $r) => auth()->user()?->can('impersonate_vendor'))`
  - `->action(function(VendorProfile $record): void { $token = app(ImpersonateVendorAction::class)->execute($record, auth()->user()); Notification::make()->title(__('identity.notifications.impersonation_token'))->body($token)->info()->persistent()->send(); })`

---

## Phase 6: User Story 4 â€” Tabbed Detail View (P4)

**Story goal**: Admin opens vendor detail page and sees all related data in tabs (Services, Bookings, Wallet, Withdrawals, Reviews, Activity).

**Independent test**: Navigate to ViewVendorProfile for a vendor with data in each related table â†’ all tabs render without 500 error.

- [X] T019 [US4] Create `app/Modules/Identity/Filament/Resources/VendorProfileResource/RelationManagers/ServicesRelationManager.php` â€” `RelationManager` with `$relationship = 'services'`. Table columns: `public_id` (copyable), `name` (translated), `product_type` (badge with color map), `status` (badge), `created_at`. Read-only: `canCreate()=false`, `canEdit()=false`, `canDelete()=false`. Uses `DB::table('services')->where('vendor_profile_id',$this->ownerRecord->id)` or the `services()` hasMany relationship on `VendorProfile` model (verify relationship exists; add if missing).

- [X] T020 [P] [US4] Create `app/Modules/Identity/Filament/Resources/VendorProfileResource/RelationManagers/BookingVendorsRelationManager.php` â€” queries `booking_vendors` joined to `bookings` for `$this->ownerRecord->id`. Columns: booking `public_id`, `lifecycle_status` (badge), `total_minor` (money), `created_at`. Read-only.

- [X] T021 [P] [US4] Create `app/Modules/Identity/Filament/Resources/VendorProfileResource/RelationManagers/WalletRelationManager.php` â€” shows the vendor's wallet row (`wallets`) plus last 50 `wallet_ledger` entries. Use a `TableWidget` pattern or a single stats section showing `balance_minor` + a `Table` of ledger entries. Columns: `entry_type`, `amount_minor` (money), `description`, `created_at`. Read-only. Query via `DB::table('wallets')->where('owner_type','vendor_profile')->where('owner_id',$this->ownerRecord->id)`.

- [X] T022 [P] [US4] Create `app/Modules/Identity/Filament/Resources/VendorProfileResource/RelationManagers/WithdrawalsRelationManager.php` â€” queries `withdrawals` joined to `wallets` where wallet's `owner_id = $this->ownerRecord->id`. Columns: `public_id`, `amount_minor` (money), `status` (badge), `requested_at`, `processed_at`. Read-only.

- [X] T023 [P] [US4] Create `app/Modules/Identity/Filament/Resources/VendorProfileResource/RelationManagers/VendorReviewsRelationManager.php` â€” queries `vendor_reviews` where `vendor_profile_id = $this->ownerRecord->id`. Columns: `public_id`, `rating` (star display), `body` (translated, limited), `created_at`. Read-only.

- [X] T024 [P] [US4] Create `app/Modules/Identity/Filament/Resources/VendorProfileResource/RelationManagers/ActivityLogRelationManager.php` â€” queries `activity_log` (spatie's table) where `subject_type = VendorProfile::class AND subject_id = $this->ownerRecord->id` ordered by `created_at DESC`. Columns: `description` (log event name), `causer_id` (admin name via join on `users`), `properties` (JSON preview), `created_at`. Read-only.

- [X] T025 [US4] Update `app/Modules/Identity/Filament/Resources/VendorProfileResource/Pages/ViewVendorProfile.php` to override `getRelationManagers(): array` and return all 6 RelationManager classes from T019â€“T024.

---

## Phase 7: User Story 5 â€” Document Re-upload (P5)

**Story goal**: Admin replaces a vendor document (file_path updated, old S3 file deleted).

**Independent test**: Click re-upload action on a `vendor_documents` row â†’ upload new file â†’ verify `file_path` in DB is updated.

- [X] T026 [US5] Create `app/Modules/Identity/Filament/Resources/VendorProfileResource/RelationManagers/DocumentsRelationManager.php` â€” queries `vendor_documents` where `vendor_profile_id = $this->ownerRecord->id`. Columns: `doc_type` (badge), `file_name`, `status` (badge), `reviewed_at`. Add per-row `Action::make('reUpload')` with `->form([FileUpload::make('file')->required()->maxSize(10240)->disk('s3')->directory('vendor-documents/private')])` and `->action(function(VendorDocument $record, array $data): void { $oldPath=$record->file_path; $record->update(['file_path'=>$data['file'],'file_name'=>basename($data['file']),'status'=>'pending']); activity()->on($record)->causedBy(auth()->user())->log('document_re_uploaded'); DB::afterCommit(fn()=>Storage::disk('s3')->delete($oldPath)); Notification::make()->title(__('identity.notifications.document_uploaded'))->success()->send(); })`. Requires `re_upload_vendor_document` permission check in `->visible()`.

- [X] T027 [US5] Add `DocumentsRelationManager::class` to `ViewVendorProfile::getRelationManagers()` array (insert as first tab or after Overview, before Services).

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T028 Run `php artisan shield:generate --all` and verify no permission generation errors. Confirm `impersonate_vendor` and `re_upload_vendor_document` permissions appear in the Shield config.

- [ ] T029 Verify `VendorProfileResource` navigation group is `'Vendor Management'` (separate from `'Vendor Onboarding'` used by `VendorApprovalQueueResource`) and add `->navigationSort(3)` to distinguish position.

- [X] T030 Write `tests/Feature/Modules/Identity/AdminVendorManagementTest.php` with the following 6 Pest cases:
  1. `it('edit preserves approval_status when admin saves vendor profile')` â€” setup approved vendor, PATCH profile fields, assert `approval_status` unchanged
  2. `it('impersonation creates audit_logs entry before returning token')` â€” act as `super_admin`, trigger impersonation, assert `audit_logs` row exists with `action='vendor_impersonated'` and token is returned as non-empty string
  3. `it('unauthorized impersonation returns 403')` â€” act as regular admin (no `impersonate_vendor` permission), trigger impersonation, assert 403 / access denied
  4. `it('type revocation with empty reason is rejected')` â€” attempt revocation with empty `revoke_reason_en`, assert form validation error
  5. `it('type revocation stores bilingual reason in vendor_approved_product_types')` â€” revoke with `{en:'reason',ar:'Ø³Ø¨Ø¨'}`, assert DB row has matching JSON
  6. `it('coverage area replace is transactional')` â€” trigger AdminReplaceVendorCoverageAction with a bad city_id mid-array, assert no partial writes (all-or-nothing)

- [ ] T031 Run `./vendor/bin/pest tests/Feature/Modules/Identity/AdminVendorManagementTest.php` â€” all 6 tests must pass

- [ ] T032 Run `./vendor/bin/pint app/Modules/Identity/` to auto-fix code style on all modified files

- [ ] T033 Run `./vendor/bin/phpstan analyse app/Modules/Identity/` and resolve any type errors introduced in this phase

---

## Dependency Graph

```
T001 â†’ T002 â†’ T003          (Setup â€” in order)
T004 â†’ T005                 (Lang keys â€” EN before AR)
T006 â†’ T018                 (ImpersonateVendorAction â†’ impersonate Filament action)
T007 â†’ T010                 (AdminReplaceVendorCoverageAction â†’ EditVendorProfile page)
T008 â†’ T010                 (UpdateVendorProfileAction fix â†’ EditVendorProfile page)
T009 â†’ T010 â†’ T011 â†’ T012 â†’ T013 â†’ T014   (VendorResource form chain â€” sequential)
T015 â†’ T016 â†’ T017          (Revocation validation chain)
T019 â†’ T025                 (All RelationManagers must exist before ViewVendorProfile registers them)
T020, T021, T022, T023, T024 â†’ T025   (Parallel RelationManager creation)
T026 â†’ T027                 (DocumentsRelationManager â†’ ViewVendorProfile)
T028 â†’ T029 â†’ T030 â†’ T031 â†’ T032 â†’ T033   (Polish in order)
```

## Parallel Execution Opportunities

Within **Phase 3 (US1)**: T009, T010, T011 must be sequential (form â†’ page â†’ subsections). T013 and T014 can be done after T010.

Within **Phase 6 (US4)**: T020, T021, T022, T023, T024 are fully parallel (each is a separate file with no shared state). They can be dispatched simultaneously.

**T017** (notification listener for VendorTypeRevoked) is independent of all Filament work and can run in parallel with Phase 3 tasks.

## Implementation Strategy

**MVP scope**: Complete Phase 1 (Setup) + Phase 2 (Actions) + Phase 3 (US1 â€” Edit form) first. This delivers the primary admin capability (edit without losing approval state) and proves the action chain.

**Then**: Phase 4 (US2 â€” Revocation wiring, T015â€“T017) is very fast since `RevokeVendorTypeAction` already exists; mostly verification.

**Then**: Phase 5 (US3 â€” Impersonation, T018) is a single Filament action wiring task.

**Then**: Phase 6 (US4 â€” Tabbed view, T019â€“T025) â€” all RelationManagers can be written in parallel.

**Then**: Phase 7 (US5 â€” Document re-upload, T026â€“T027) and Phase 8 (Polish).

---

## Summary

| Metric | Count |
|--------|-------|
| Total tasks | 33 |
| Setup tasks (Phase 1) | 5 |
| Foundational tasks (Phase 2) | 3 |
| US1 â€” Edit form tasks | 6 |
| US2 â€” Revocation tasks | 3 |
| US3 â€” Impersonation tasks | 1 |
| US4 â€” Tabbed view tasks | 7 |
| US5 â€” Document re-upload tasks | 2 |
| Polish tasks | 6 |
| Parallel opportunities | T020â€“T024 (5 parallel RelationManagers), T017 (parallel listener) |
| Migrations required | 0 (all tables exist) |
| New action files | 2 (`ImpersonateVendorAction`, `AdminReplaceVendorCoverageAction`) |
| New Filament files | 8 (EditVendorProfile page + 6 RelationManagers + DocumentsRelationManager) |

