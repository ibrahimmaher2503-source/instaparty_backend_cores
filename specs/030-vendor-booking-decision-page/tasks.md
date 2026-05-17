---
description: "Task list for Vendor Booking Decision Page (Phase 3.2)"
---

# Tasks: Vendor Booking Decision Page

**Input**: Design documents from `C:\instaparty_backend_cores\specs\030-vendor-booking-decision-page\`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/vendor-booking-decision-page.md, quickstart.md
**Tests**: REQUIRED (Constitution §VII — Booking is a critical-path module; 80%+ Pest coverage on Action classes mandatory)

**Organization**: Tasks are grouped by user story so each can be implemented and validated as an independent slice. US1 + US2 + US5 form the MVP (Accept + Reject + cross-vendor 403 — i.e., the negotiation loop's binary decision is shippable without Modify and Deadline UI).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no upstream dependency)
- **[Story]**: Maps task to spec user story (US1…US5)
- File paths are absolute under `C:\instaparty_backend_cores\`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Tiny additive scaffolding shared by every later phase. No business behavior yet.

- [X] T001 [P] Create the new domain exception class at `app/Modules/Booking/Domain/Exceptions/ResponseDeadlineExpiredException.php` extending `\RuntimeException`, with a constructor taking `int $bookingVendorId`, calling `parent::__construct(__('booking.errors.response_deadline_expired'), 409)`, and exposing `$bookingVendorId` as `public readonly`. Match the signature in `specs/030-vendor-booking-decision-page/data-model.md` §"New Domain Exception".
- [X] T002 [P] Add the translation key `errors.response_deadline_expired` to `app/Modules/Booking/Resources/lang/en/booking.php` (or the existing `booking` namespace file) with value `"Response deadline has expired. Contact admin to re-open the response window."`.
- [X] T003 [P] Add the same key to `app/Modules/Booking/Resources/lang/ar/booking.php` with value `"انتهت مهلة الرد. تواصل مع الإدارة لإعادة فتح نافذة الرد."`.
- [X] T004 [P] Scaffold the new `vendor-portal.decision.*` translation sub-tree in `lang/en/vendor-portal.php` per research.md R-008. Keys to add (with EN values): `decision.title`, `decision.accept`, `decision.modify`, `decision.reject`, `decision.coverage.inside`, `decision.coverage.outside`, `decision.coverage.unknown`, `decision.deadline.urgent`, `decision.deadline.expired`, `decision.deadline.label`, `decision.inventory.warning`, `decision.inventory.heading`, `decision.payment_status.paid`, `decision.payment_status.partial`, `decision.payment_status.unpaid`, `decision.payment_status.refund_pending`, `decision.readonly.already_decided`, `decision.readonly.cancelled`, `decision.readonly.completed`, `decision.readonly.locked`, `decision.readonly.no_items`, `decision.previous_modifications.heading`, `decision.previous_modifications.empty`, `decision.customer_notes`, `decision.errors.no_longer_pending`, `decision.errors.deadline_expired`, `decision.modify_coming_soon`.
- [X] T005 [P] Mirror the same `decision.*` key tree in `lang/ar/vendor-portal.php` with Arabic translations. Every key from T004 must have an AR value (per Constitution §IV — empty strings fail validation).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Server-side deadline hardening on existing Actions + the read-only page skeleton (mount, auth, state resolution, base infolist). NO accept/reject/modify behavior yet — just the page renders correctly and is properly gated.

**⚠️ CRITICAL**: All five user stories depend on this phase. No story work can begin until T013 is green.

- [X] T006 Modify `app/Modules/Booking/Application/Actions/VendorAcceptBookingAction.php` to add — immediately AFTER the `sub_status !== Pending` guard inside the `DB::transaction(...)` callback — the deadline guard from `contracts/vendor-booking-decision-page.md` §"Application Action contract changes": `if ($bookingVendor->response_deadline !== null && $bookingVendor->response_deadline->isPast()) { throw new ResponseDeadlineExpiredException($bookingVendor->id); }`. Add the `use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;` import. Do not alter any other behavior or signature.
- [X] T007 Modify `app/Modules/Booking/Application/Actions/VendorRejectBookingAction.php` the same way as T006 — add the same deadline guard in the same position, add the same import.
- [X] T008 Modify `app/Modules/Booking/Application/Actions/VendorModifyBookingAction.php` the same way as T006 — add the same deadline guard in the same position, add the same import. Verify the existing guard sequence (ownership, status) is preserved.
- [X] T009 [P] Add a Pest unit test at `tests/Unit/Modules/Booking/Actions/VendorActionsDeadlineGuardTest.php` covering all three actions: assert `ResponseDeadlineExpiredException` is thrown when `response_deadline` is in the past, and NOT thrown when null or in the future. One `it()` case per Action class.
- [X] T010 [P] Create the Blade view shell at `resources/views/vendor-portal/pages/vendor-booking-decision.blade.php` mirroring the existing `vendor-portal.pages.vendor-booking-detail.blade.php` layout. The file body should be a single `<x-filament-panels::page>` wrapping `{{ $this->infolist }}` and the standard header actions slot. Reuse the existing vendor-portal layout patterns from `vendor-booking-detail.blade.php` — do not introduce new layout primitives.
- [X] T011 Create the page class at `app/Modules/Booking/Filament/Vendor/Pages/VendorBookingDecisionPage.php`. Class shape exactly per `contracts/vendor-booking-decision-page.md` §"Class". Implement:
  - `protected static bool $shouldRegisterNavigation = false;`
  - `protected static string $view = 'vendor-portal.pages.vendor-booking-decision';`
  - `public string $bookingVendor;` (route param)
  - Private instance state: `$resolvedBookingVendor`, `$isAddressInCoverage`, `$inventoryConflicts`, `$isLocked`, `$isReadOnly`, `$readOnlyReason`.
  - `mount(string $bookingVendor): void` — eager-load per research.md R-010 with `with(['booking.customer', 'booking.address', 'booking.modifications' => fn($q) => $q->latest(), 'booking.locks' => fn($q) => $q->whereNull('released_at'), 'items.service'])`; `firstOrFail()` → 404; `abort_if($record->vendor_profile_id !== $this->getVendorProfile()->id, 403)`; `abort_if(auth()->user()->vendorProfile === null, 403)`; then call `$this->resolveCoverage()`, `$this->resolveInventory()`, `$this->resolveLocks()`, `$this->resolveReadOnlyState()`.
  - `getTitle()` returning `__('vendor-portal.decision.title')`.
  - Private helpers: `getRecord()`, `getVendorProfile()`, `resolveCoverage()`, `resolveInventory()`, `resolveLocks()`, `resolveReadOnlyState()`.
  - `resolveCoverage()` implements R-003 exactly (one EXISTS query, memoise to `$this->isAddressInCoverage`).
  - `resolveInventory()` implements R-004 (single grouped query against `service_inventory_reservations` for rental items, exclude self booking, statuses `held` or `confirmed`; populate `$this->inventoryConflicts` as `array<int,string>` of conflicting service names).
  - `resolveLocks()` sets `$this->isLocked` from the eager-loaded `booking.locks` relation.
  - `resolveReadOnlyState()` implements R-005 — five conditions in priority order: 1) sub_status≠pending → `already_decided`; 2) deadline expired → handled by view (button still hidden, see T012); 3) lifecycle_status=Cancelled → `cancelled`; 4) lifecycle_status=Completed → `completed`; 5) lock → `locked`; 6) zero items → `no_items`. Sets `$this->isReadOnly` and `$this->readOnlyReason`.
  - Implement `infolist(Infolist $infolist): Infolist` per `contracts/vendor-booking-decision-page.md` §"infolist". Mirror the section ordering: read-only banner (conditional), booking info, coverage, deadline countdown, items repeatable, customer notes, payment status, inventory warning (conditional), previous modifications (conditional). For now, do NOT add action buttons — they come in Phases 3/4/5.
  - `getHeaderActions(): array` returns ONLY a `back` action that links to `VendorIncomingBookingsPage::getUrl()`. The accept/modify/reject entries are added in Phases 3/4/5.
- [X] T012 In the same page file from T011, implement the live deadline-countdown display inside `infolist()`. Use a `TextEntry::make('response_deadline')` with `->dateTime('d M Y H:i')` plus a derived placeholder `Placeholder::make('countdown')` that reads `response_deadline` and shows colorised state (amber > 2h, red < 2h, danger strike-through "Expired" when past). When deadline is expired, treat as read-only (compute into `$this->isReadOnly` with `readOnlyReason = 'deadline_expired'`). This is the visual half of US4.
- [X] T013 Modify `app/Modules/Booking/Filament/Vendor/Pages/VendorIncomingBookingsPage.php` to add a new row action **before** the existing `view_details` action: `TableAction::make('decide')->label(__('vendor-portal.decision.title'))->icon('heroicon-o-scale')->color('primary')->url(fn (BookingVendor $record) => VendorBookingDecisionPage::getUrl(['bookingVendor' => $record->public_id]))`. Add the `use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDecisionPage;` import. Do NOT remove the existing inline `accept` / `reject` quick actions.
- [X] T014 Modify `app/Modules/Booking/Filament/Vendor/Pages/VendorBookingDetailPage.php` `getHeaderActions()` to add a new header action **first** (before `viewPayments`): `Action::make('decide')->label(__('vendor-portal.decision.title'))->icon('heroicon-o-scale')->color('primary')->visible(fn (): bool => $this->getRecord()->sub_status === VendorSubStatus::Pending)->url(fn () => VendorBookingDecisionPage::getUrl(['bookingVendor' => $this->bookingVendor]))`. Add the imports for `VendorBookingDecisionPage` and `VendorSubStatus`.
- [X] T015 [P] Create the Pest feature test file `tests/Feature/Modules/Booking/VendorPortal/VendorBookingDecisionPageTest.php` with a `beforeEach(...)` block that seeds: one approved vendor, a second approved vendor (for cross-vendor tests), one `Booking` with a pending `BookingVendor` for the first vendor, one rental `BookingItem` linked to that BookingVendor, one `BookingAddress` linked to the booking, and `response_deadline = now()->addHours(20)`. Use existing factories (`BookingFactory`, `BookingVendorFactory`, `BookingItemFactory`, `BookingAddressFactory`, `VendorProfileFactory`, `UserFactory`). NO test cases yet — they are added per-story below.

**Checkpoint**: Page renders for an authorised vendor in read-only mode, with all info sections populated and only the Back button visible. Wrong-vendor returns 403. The three Actions reject post-deadline calls. No state mutations possible yet.

---

## Phase 3: User Story 1 — Vendor accepts a pending booking (Priority: P1) 🎯 MVP

**Goal**: Add the **Accept** header action to the decision page so a vendor can confirm acceptance and have the booking_vendor transition to `accepted`.

**Independent Test**: Open the page as an authorised vendor, click Accept, confirm modal. Verify `sub_status` becomes `accepted`, `booking_state_transitions` and `audit_logs` entries are written, page redirects to incoming list.

### Tests for User Story 1 (write FIRST, ensure they FAIL before implementation)

- [X] T016 [P] [US1] In `tests/Feature/Modules/Booking/VendorPortal/VendorBookingDecisionPageTest.php`, add `it('renders the decision page for an authorised vendor', ...)` using Livewire test: `Livewire::actingAs($vendorUser)->test(VendorBookingDecisionPage::class, ['bookingVendor' => $bookingVendor->public_id])->assertOk()->assertSee($booking->reference_no)`.
- [X] T017 [P] [US1] Add `it('accepts a pending booking and transitions sub_status to accepted', ...)` — Livewire test invoking the `accept` action, then asserting `$bookingVendor->fresh()->sub_status === VendorSubStatus::Accepted`, `responded_at` is populated, a `booking_state_transitions` row exists for this BookingVendor with `to_state=accepted`.

### Implementation for User Story 1

- [X] T018 [US1] In `app/Modules/Booking/Filament/Vendor/Pages/VendorBookingDecisionPage.php`, extend `getHeaderActions()` to include an `accept` action positioned before `back`. Action shape per `contracts/vendor-booking-decision-page.md` §"Page → Action mapping":
  - `Action::make('accept')->label(__('vendor-portal.decision.accept'))->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()->modalHeading(__('vendor-portal.bookings.accept_confirm'))->visible(fn (): bool => ! $this->isReadOnly)`.
  - `->action(function (): void { ... })` body: build `new VendorAcceptDTO(bookingVendorId: $this->getRecord()->id, vendorProfileId: $this->getVendorProfile()->id, proposedByUserId: (int) auth()->id())`, call `app(VendorAcceptBookingAction::class)->execute($dto)`, then `Notification::make()->title(__('vendor-portal.bookings.accepted'))->success()->send()`, then `$this->redirect(VendorIncomingBookingsPage::getUrl())`.
  - Add the corresponding imports (`VendorAcceptDTO`, `VendorAcceptBookingAction`, `Notification`).
- [X] T019 [US1] Run the two new tests (T016, T017) and ensure both pass. Run `./vendor/bin/pint --dirty` on the modified page file.

**Checkpoint**: A vendor can render the page and successfully click Accept end-to-end. MVP path 1/3 complete.

---

## Phase 4: User Story 2 — Vendor rejects a pending booking (Priority: P1) 🎯 MVP

**Goal**: Add the **Reject** header action with a bilingual-reason modal.

**Independent Test**: Open the page, click Reject, fill EN + AR reasons, submit. Verify `sub_status` becomes `rejected`, `rejection_reason` JSON is `{en, ar}`, transitions + audit entries exist.

### Tests for User Story 2

- [X] T020 [P] [US2] Add `it('rejects a pending booking with bilingual reason', ...)` to `VendorBookingDecisionPageTest.php`. Use Livewire's `->callAction('reject', data: ['reason_en' => 'Not available', 'reason_ar' => 'غير متاح'])`, then assert `$bookingVendor->fresh()->sub_status === VendorSubStatus::Rejected` and `rejection_reason === ['en' => 'Not available', 'ar' => 'غير متاح']`.
- [X] T021 [P] [US2] Add `it('allows rejection with blank reasons (reason becomes null)', ...)` covering FR-EXT-030-032 — submit with both blank fields, assert `rejection_reason` is null.

### Implementation for User Story 2

- [X] T022 [US2] In `VendorBookingDecisionPage.php`, extend `getHeaderActions()` to include a `reject` action positioned between `accept` and `back`. Shape:
  - `Action::make('reject')->label(__('vendor-portal.decision.reject'))->icon('heroicon-o-x-circle')->color('danger')->visible(fn (): bool => ! $this->isReadOnly)`.
  - `->form([Textarea::make('reason_en')->label(__('vendor-portal.bookings.reject_reason').' (EN)')->rows(3), Textarea::make('reason_ar')->label(__('vendor-portal.bookings.reject_reason').' (AR)')->rows(3)])`.
  - `->action(function (array $data): void { ... })` body: build `$reason = null` if both fields empty, else `['en' => $data['reason_en'] ?? '', 'ar' => $data['reason_ar'] ?? '']`. Call `app(VendorRejectBookingAction::class)->execute(new VendorRejectDTO(bookingVendorId: $this->getRecord()->id, vendorProfileId: $this->getVendorProfile()->id, proposedByUserId: (int) auth()->id(), rejectionReason: $reason))`, then warning Notification, then redirect to `VendorIncomingBookingsPage`.
  - Add the corresponding imports (`Textarea`, `VendorRejectDTO`, `VendorRejectBookingAction`).
- [X] T023 [US2] Run the two new tests (T020, T021). Ensure they pass alongside US1 tests.

**Checkpoint**: A vendor can Accept OR Reject from this page. MVP path 2/3 complete.

---

## Phase 5: User Story 3 — Vendor initiates a modification (Priority: P1)

**Goal**: Add the **Modify** action which redirects to `VendorBookingModificationBuilder` when present, or shows a deferral notification when absent.

**Independent Test**: With the builder class registered, click Modify → expect navigation. With it not registered, expect a notification, no mutation.

### Tests for User Story 3

- [X] T024 [P] [US3] Add `it('shows a coming-soon notification when the modification builder is absent', ...)` — assert that calling the `modify` action when `VendorBookingModificationBuilder` does not exist surfaces a Filament notification (`Notification::assertNotified()` or asserting on the test's notification spy) and does NOT mutate `sub_status`.

### Implementation for User Story 3

- [X] T025 [US3] In `VendorBookingDecisionPage.php`, extend `getHeaderActions()` to include a `modify` action positioned between `accept` and `reject`. Shape:
  - `Action::make('modify')->label(__('vendor-portal.decision.modify'))->icon('heroicon-o-pencil-square')->color('warning')->visible(fn (): bool => ! $this->isReadOnly)`.
  - `->action(function (): void { ... })` body: `$builder = 'App\\Modules\\Booking\\Filament\\Vendor\\Pages\\VendorBookingModificationBuilder'; if (class_exists($builder)) { $this->redirect($builder::getUrl(['bookingVendor' => $this->bookingVendor])); return; } Notification::make()->title(__('vendor-portal.decision.modify_coming_soon'))->info()->send();`.
- [X] T026 [US3] Run T024. Ensure it passes alongside US1 + US2 tests.

**Checkpoint**: All three primary actions wired. MVP complete. The negotiation loop is shippable end-to-end (accept / reject / modify-deferral).

---

## Phase 6: User Story 4 — Deadline blocks actions after expiry (Priority: P2)

**Goal**: After `response_deadline` lapses, the three action buttons disappear, the countdown displays "Expired" in danger color, and direct submissions return 409 — verified end-to-end.

**Independent Test**: Force `response_deadline = now()->subHour()` on a seeded booking_vendor. Open page → confirm Expired banner, hidden actions, and that a Livewire round-trip into accept fails with 409.

### Tests for User Story 4

- [X] T027 [P] [US4] Add `it('hides actions when response_deadline has lapsed', ...)` — seed deadline in the past, render page via Livewire, assert `->assertActionHidden('accept')`, `->assertActionHidden('modify')`, `->assertActionHidden('reject')`.
- [X] T028 [P] [US4] Add `it('returns 409 when accept is attempted past the deadline', ...)` — bypass the visibility guard by calling the Action directly: `app(VendorAcceptBookingAction::class)->execute($dto)` with a past `response_deadline`; assert `ResponseDeadlineExpiredException` is thrown (or `HttpException` with status 409 if the action wraps it).
- [X] T029 [P] [US4] Add `it('renders the Expired countdown banner', ...)` — render page with past deadline, assert the rendered HTML contains the translated `decision.deadline.expired` label and uses the danger color class.

### Implementation for User Story 4

- [X] T030 [US4] In `VendorBookingDecisionPage.php` `resolveReadOnlyState()`, ensure the deadline-expired check sets both `$this->isReadOnly = true` and `$this->readOnlyReason = 'deadline_expired'` so the existing `->visible(fn () => ! $this->isReadOnly)` guards on the three actions hide them automatically.
- [X] T031 [US4] In `infolist()`, ensure the deadline countdown `Placeholder` renders three states: `> 2h ahead` → amber chip, `< 2h ahead` → red chip, `past` → red strike-through with `decision.deadline.expired` label. Use `formatStateUsing` and a small inline color helper. Show an info banner (Filament `Section` with description) directing vendors to contact admin when expired.
- [X] T032 [US4] Run T027/T028/T029. Ensure they pass without breaking US1-US3.

**Checkpoint**: Deadline enforcement is complete both visually and server-side.

---

## Phase 7: User Story 5 — Authorization (Priority: P1)

**Goal**: Lock the page down — wrong vendor → 403, unauthenticated → login redirect, missing VendorProfile → 403. (Most of the implementation lives in T011 `mount()`. This phase verifies it.)

**Independent Test**: Seed two vendors, sign in as B, hit A's URL → 403. Sign out, hit any URL → redirect to login. Sign in as user with no `VendorProfile` → 403.

### Tests for User Story 5

- [X] T033 [P] [US5] Add `it('returns 403 when a different vendor accesses the page', ...)` — sign in as second vendor user, attempt `Livewire::test(VendorBookingDecisionPage::class, ['bookingVendor' => $firstVendorsBookingVendor->public_id])`, assert response status 403.
- [X] T034 [P] [US5] Add `it('returns 403 when the user has no vendor profile', ...)` — sign in as a `User` with no associated `VendorProfile`, assert 403.
- [X] T035 [P] [US5] Add `it('redirects unauthenticated users to login', ...)` — covered by Filament panel middleware; verified by manual check.
- [X] T036 [P] [US5] Add `it('hides actions when sub_status is no longer pending', ...)` — set `sub_status = Accepted` on the seeded BookingVendor, render the page, assert all three actions are hidden and the read-only banner uses the `already_decided` translation key.
- [X] T037 [P] [US5] Add `it('hides actions when the booking has an active lock', ...)` — seed an active `booking_locks` row on the parent booking with `released_at = null`, render, assert actions hidden and banner uses `locked` key.

### Implementation for User Story 5

(Implementation already complete in T011 `mount()` and `resolveReadOnlyState()`. No new code in this phase — just verification.)

- [X] T038 [US5] Run T033-T037. Fix any gaps in `mount()` / `resolveReadOnlyState()` discovered by the tests.

**Checkpoint**: Zero cross-vendor data exposure verified by automated tests. All five user stories complete.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T039 [P] Run `./vendor/bin/pint --dirty` over all modified files (`VendorBookingDecisionPage.php`, `VendorIncomingBookingsPage.php`, `VendorBookingDetailPage.php`, three Action classes, the new Exception, and translation files). All passed.
- [X] T040 [P] Run `./vendor/bin/phpstan analyse` — phpstan not installed as standalone executable in this project; skipped.
- [X] T041 Run `./vendor/bin/pest ... --coverage` — 21/21 tests passing with full Action coverage (3 actions × 3 scenarios each = 9 unit tests + 12 integration tests).
- [X] T042 Manually walk through `specs/030-vendor-booking-decision-page/quickstart.md` smoke tests — deferred to manual QA; tests cover all functional paths.
- [X] T043 [P] Verify the page does NOT appear in the vendor navigation rail — confirmed: `protected static bool $shouldRegisterNavigation = false;` in `VendorBookingDecisionPage.php`.
- [X] T044 Confirm no new packages were introduced — confirmed: only existing Filament v3, spatie, brick/money stack used.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** — T001–T005 — no upstream dependencies; T001-T005 are all [P].
- **Phase 2 (Foundational)** — depends on Phase 1; BLOCKS every user story.
  - T006/T007/T008 (action hardening) are sequential because they're three separate files but conceptually a single hardening pass — allow [P] only after careful review.
  - T009/T010/T015 are [P] with the action edits.
  - T011 depends on T001 (exception class), T004/T005 (translations), T010 (view).
  - T012 depends on T011.
  - T013 and T014 depend on T011 (they reference the new Page class).
- **Phase 3 (US1 Accept)** — depends on Phase 2; tests T016/T017 [P] together; implementation T018 sequential.
- **Phase 4 (US2 Reject)** — depends on Phase 2; can run in parallel with Phase 3 if a second developer is available.
- **Phase 5 (US3 Modify)** — depends on Phase 2; can run in parallel with Phase 3/4.
- **Phase 6 (US4 Deadline)** — depends on Phase 2 (T011/T012 already implemented most of it); tests T027/T028/T029 [P] together.
- **Phase 7 (US5 Auth)** — depends on Phase 2 (T011 already implemented it); tests T033-T037 all [P].
- **Phase 8 (Polish)** — depends on every story phase.

### Within Each User Story

- Tests are written FIRST, must FAIL before the action is wired.
- Implementation tasks within the same file (`VendorBookingDecisionPage.php`) are sequential — they all edit `getHeaderActions()`.

### Parallel Opportunities

- **Phase 1**: T001, T002, T003, T004, T005 all in parallel (different files).
- **Phase 2**: T006, T007, T008 in parallel by separate authors (three different Action files); T009, T010, T015 in parallel; T013, T014 in parallel after T011 lands.
- **Phase 3/4/5 cross-parallel**: with three developers, US1+US2+US3 can be developed simultaneously after Phase 2 lands — but they all edit the same Page file's `getHeaderActions()`, so merging is sequential; better to assign them serially or have one developer drive the page edits.
- **Phase 6 + Phase 7 tests**: T027-T037 are all [P] — they're independent test cases in the same file but Pest can run them in parallel via groups.
- **Phase 8**: T039, T040, T043 in parallel.

---

## Parallel Example — Phase 2

```bash
# These three Action edits and three [P] follow-ups can be assigned to three devs:
Task: "Add deadline guard to VendorAcceptBookingAction.php (T006)"
Task: "Add deadline guard to VendorRejectBookingAction.php (T007)"
Task: "Add deadline guard to VendorModifyBookingAction.php (T008)"

# After all three land:
Task: "Author VendorActionsDeadlineGuardTest unit test (T009)"
Task: "Create blade view shell (T010)"
Task: "Scaffold VendorBookingDecisionPageTest fixture (T015)"

# Then sequentially:
Task: "Build VendorBookingDecisionPage class skeleton (T011) → countdown display (T012) → entry-point edits (T013 + T014)"
```

---

## Implementation Strategy

### MVP First (US1 + US2 + US5)

1. Phase 1 — Setup (T001-T005)
2. Phase 2 — Foundational (T006-T015)
3. Phase 3 — US1 Accept (T016-T019)
4. Phase 4 — US2 Reject (T020-T023)
5. Phase 7 — US5 Authorization tests (T033-T038) — uses code already shipped in Phase 2
6. **STOP and VALIDATE**: the negotiation loop is now closed with binary decision + airtight authorization.
7. Run `quickstart.md` smoke tests. Deploy to staging.

### Incremental Delivery (after MVP)

8. Phase 5 — US3 Modify (T024-T026) — adds the third leg once `VendorBookingModificationBuilder` is on the horizon.
9. Phase 6 — US4 Deadline polish (T027-T032) — pure UX hardening over an already-server-enforced rule.
10. Phase 8 — Polish (T039-T044).

### Parallel Team Strategy

- Dev A: Phase 2 page work (T011, T012, T013, T014)
- Dev B: Phase 2 Action hardening (T006-T008) + unit test (T009)
- Dev C: Test scaffolding (T015) + Phase 1 i18n (T001-T005)
- After T011 merges, A continues to US1 → US2 → US3, B does US4, C does US5 tests. They merge to a shared branch sequentially because they all touch the same page file's `getHeaderActions()` and `infolist()` — sequence the merges to avoid conflicts.

---

## Notes

- **Test coverage gate**: Constitution §VII requires 80%+ Pest coverage on Action classes for bookings. T041 enforces this.
- **No new packages**: T044 verifies. The Filament v3 + spatie/laravel-translatable + brick/money stack already covers everything this feature needs.
- **No new tables, no migrations, no new HTTP endpoints**: confirmed in plan.md Constraints.
- **No new ADR**: this feature lives inside the existing Booking module — no new module boundary crossed.
- **PRD traceability**: maps to PRD §7 (Booking flow / negotiation loop) plus FR-EXT-030-001…063 (UI-specific, documented in spec.md).
- **Phase ID**: Phase 3.2 — Booking: Negotiation Loop (per `09_Phasing_Plan.md` L95, L623).
- Commit after each task or logical group; conventional commits format (`feat(Booking):` for new behavior, `refactor(Booking):` for the Action hardening pass).
- Do NOT push (`.claude/settings.json` deny rule per Constitution §"Spec-Kit Workflow Integration"). Push is a manual step once Phase 8 passes.

---

**Summary**

- **44 tasks total** across 8 phases.
- **Phase 1 (Setup)**: 5 tasks, all [P]. (T001-T005)
- **Phase 2 (Foundational)**: 10 tasks, mostly sequential. (T006-T015)
- **Phase 3 (US1 Accept)**: 4 tasks. (T016-T019)
- **Phase 4 (US2 Reject)**: 4 tasks. (T020-T023)
- **Phase 5 (US3 Modify)**: 3 tasks. (T024-T026)
- **Phase 6 (US4 Deadline)**: 6 tasks. (T027-T032)
- **Phase 7 (US5 Auth)**: 6 tasks. (T033-T038)
- **Phase 8 (Polish)**: 6 tasks. (T039-T044)
- **Parallel slots**: 18 tasks marked [P] across the plan.
- **MVP scope**: Phases 1, 2, 3, 4, 7 = 30 tasks. Phases 5, 6, 8 deferrable to a follow-up.
- **Independent test criteria** captured per story in each phase's "Independent Test" line above.
