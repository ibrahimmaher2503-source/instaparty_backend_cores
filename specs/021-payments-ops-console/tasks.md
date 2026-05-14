# Tasks: Payments Operations Console

**Input**: Design documents from `specs/021-payments-ops-console/`
**Phase**: 4.3 — Payments Operations Console (⚠️ PHASE BACKFILL NEEDED)
**Branch**: `021-payments-ops-console`
**ADR Gate**: ADR-0019 must be `Accepted` before T002 (migrations) can begin

**Tests**: Included — spec §"Phase Exit Criteria" and plan §16 explicitly define required Pest coverage.

**Organization**: Foundation → 6 user stories in priority order (P1→P6)

---

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel with other [P]-marked tasks in same phase (different files, no shared state)
- **[Story]**: User story this task belongs to — maps to spec.md user stories
- Each phase ends with a **Checkpoint** for independent validation

---

## Phase 1: Setup — ADR + Schema + Foundation

**Purpose**: Non-negotiable prerequisites. Nothing in Phases 2–8 can begin until T001 (ADR accepted) and T002–T003 (migrations applied).

**⚠️ CRITICAL GATE**: T001 must be `Accepted` by Ibrahim before T002–T003 run. The constitution (Principle VI) blocks all code behind ADR acceptance.

- [ ] T001 Write `docs/adr/0019-payments-ops-console.md` — document decisions on: (a) Paymob void API call shape, (b) health ping endpoint (`/status` vs synthetic probe), (c) reconciliation data source (Paymob reporting API vs `gateway_webhook_logs` count fallback), (d) idempotency mechanism for webhook replay (existing UNIQUE constraint reuse), (e) no dual-approval gate in Phase 1 but read `app_settings.dual_approval_threshold_minor` for warning logging; mark status `Accepted` after Ibrahim reviews
- [ ] T002 Create migration `app/Modules/Payments/Database/Migrations/{ts}_create_payment_chargebacks_table.php` — columns per plan §3.1: `id` (bigIncrements), `public_id` CHAR(26) UNIQUE, `payment_id` FK→payments.id restrictOnDelete, `gateway_case_id` VARCHAR(190) nullable, `reason` JSON, `status` ENUM(open,under_review,won,lost) default open, `amount_minor` BIGINT UNSIGNED, `amount_currency` CHAR(3), `opened_at` TIMESTAMP, `resolved_at` TIMESTAMP nullable, `admin_notes` JSON nullable, `created_by` FK→users.id, `updated_by` FK→users.id nullable, timestamps; charset utf8mb4; indexes: (payment_id), (status,opened_at)
- [ ] T003 Create migration `app/Modules/Payments/Database/Migrations/{ts}_create_gateway_health_pings_table.php` — columns: `id` (bigIncrements), `gateway_code` CHAR(20), `latency_ms` INT UNSIGNED, `success` TINYINT(1), `error_message` VARCHAR(500) nullable, `checked_at` TIMESTAMP useCurrent(); NO `updated_at`; charset utf8mb4; indexes: (gateway_code,checked_at), (success,checked_at)
- [ ] T004 [P] Create enum `app/Modules/Payments/Domain/Enums/ChargebackStatus.php` — cases: `Open`, `UnderReview`, `Won`, `Lost`; add `label()` method returning translated string from `chargebacks.php` lang file; add `isResolved(): bool` helper (`Won` | `Lost` returns true)
- [ ] T005 [P] Create model `app/Modules/Payments/Domain/Models/PaymentChargeback.php` — `$fillable` all columns; `$casts`: `status` → `ChargebackStatus`, `reason` → `AsArrayObject` (translatable), `admin_notes` → `AsArrayObject` (translatable), `amount_minor` → `integer`, `opened_at`/`resolved_at` → `datetime`; declare `$translatable = ['reason', 'admin_notes']` (spatie/laravel-translatable); relationship `payment()` belongsTo Payment; relationship `createdBy()` belongsTo User; no soft deletes; no business logic
- [ ] T006 [P] Create model `app/Modules/Payments/Domain/Models/GatewayHealthPing.php` — `$fillable` all columns; `$casts`: `success` → `boolean`, `checked_at` → `datetime`, `latency_ms` → `integer`; no `updated_at` (set `public $timestamps = false`; manage `checked_at` manually); no soft deletes; no business logic
- [ ] T007 [P] Create DTO `app/Modules/Payments/Application/DTOs/OpenChargebackDto.php` — properties: `Payment $payment`, `array $reason` (shape: [en, ar]), `int $amountMinor`, `string $amountCurrency`, `?string $gatewayCaseId`
- [ ] T008 [P] Create DTO `app/Modules/Payments/Application/DTOs/ResolveChargebackDto.php` — properties: `PaymentChargeback $chargeback`, `ChargebackStatus $status` (must be Won or Lost), `array $adminNotes` (shape: [en, ar])
- [ ] T009 [P] Create DTO `app/Modules/Payments/Application/DTOs/VoidResult.php` — properties: `bool $success`, `string $message`
- [ ] T010 [P] Create DTO `app/Modules/Payments/Application/DTOs/PingResult.php` — properties: `bool $success`, `int $latencyMs`, `?string $errorMessage`
- [ ] T011 Extend interface `app/Modules/Payments/Domain/Contracts/PaymentGateway.php` — add three method signatures: `void(string $gatewayRef): VoidResult`, `ping(): PingResult`, `getTodayCapturedCount(): int` (throws `GatewayReportingUnavailableException` if API unreachable); add PHPDoc for each
- [ ] T012 [P] Implement `PaymobGateway::void(string $gatewayRef): VoidResult` in `app/Modules/Payments/Infrastructure/Gateways/PaymobGateway.php` — call Paymob void endpoint (URL/shape confirmed in ADR-0019); on HTTP 200 return `VoidResult(success: true)`; on any failure return `VoidResult(success: false, message: $errorMessage)`; no throw (caller handles)
- [ ] T013 [P] Implement `PaymobGateway::ping(): PingResult` in `app/Modules/Payments/Infrastructure/Gateways/PaymobGateway.php` — lightweight GET to Paymob health/status endpoint per ADR-0019; record start time before call, compute `latencyMs`; return `PingResult` with success bool and latency; catch all exceptions → `PingResult(success: false, latencyMs: 0, errorMessage: $e->getMessage())`; never throw
- [ ] T014 [P] Implement `PaymobGateway::getTodayCapturedCount(): int` in `app/Modules/Payments/Infrastructure/Gateways/PaymobGateway.php` — call Paymob reporting API per ADR-0019; parse response and return integer count; throw `GatewayReportingUnavailableException` on failure (caller applies fallback)
- [ ] T015 [P] Create domain events (all four files): `app/Modules/Payments/Domain/Events/PaymentVoided.php` (constructor: Payment $payment), `PaymentAbandoned.php` (constructor: Payment $payment), `ChargebackOpened.php` (constructor: PaymentChargeback $chargeback), `ChargebackResolved.php` (constructor: PaymentChargeback $chargeback); each extends nothing, implements `ShouldBroadcast` if needed, has only the model property
- [ ] T016 [P] Create translation files `app/Modules/Payments/Resources/lang/en/chargebacks.php` and `.../ar/chargebacks.php` — keys: `opened_successfully`, `resolved_successfully`, `status.open`, `status.under_review`, `status.won`, `status.lost`, `error.amount_exceeds_payment`, `error.active_chargeback_exists`, `error.payment_not_captured`, `error.already_resolved`; both files must have identical key sets
- [ ] T017 Register in `app/Modules/Payments/Providers/PaymentsServiceProvider.php`: (a) new models in service container if needed; (b) event→listener wiring via `Event::listen()`: `PaymentCaptured` → `ManualCaptureAuditListener` (no-op stub for now; full wiring in T024), `PaymentVoided` → Booking module listener, `PaymentAbandoned` → Booking module listener; (c) register `PingGatewayHealthCommand` in artisan; (d) schedule command every 5 minutes in `booted()` callback

**Checkpoint — Phase 1 complete**: `php artisan migrate` runs clean; `PaymentChargeback` and `GatewayHealthPing` models are instantiable; `PaymobGateway` has all 3 new methods; 4 new events exist; ADR-0019 is `Accepted`.

---

## Phase 2: User Story 1 — Admin Recovers a Failed Payment (Priority: P1) 🎯 MVP

**Goal**: Admin sees all failed payments in a list and can Retry (new attempt) or Mark Abandoned (release inventory).

**Independent Test**: Seed a `payments` row with `status=failed`; verify Retry creates a new `payments` row linked to the same booking, and Mark Abandoned updates `status=abandoned` and releases `service_inventory_reservations`.

- [ ] T018 [US1] Create `app/Modules/Payments/Application/Actions/RetryFailedPaymentAction.php` — constructor injects `PaymentGateway` and `EloquentPaymentRepository`; `execute(Payment $payment, string $adminReason): Payment`; guard: `throw_unless($payment->status === PaymentStatus::Failed, ...)`; wrap in `DB::transaction`: call `InitiatePaymentAction::execute()` with same booking/amount/method to create a new `Payment` row (do NOT mutate original); append `audit_logs` row via `activity()->performedOn($payment)->log('payment_retry')` with `adminReason` in `properties`; `DB::afterCommit` fires `PaymentInitiated::dispatch($newPayment)`; return new Payment
- [ ] T019 [US1] Create `app/Modules/Payments/Application/Actions/MarkPaymentAbandonedAction.php` — `execute(Payment $payment, string $adminReason): Payment`; guard: `throw_unless($payment->status === PaymentStatus::Failed, ...)`; `DB::transaction`: `$payment->lockForUpdate()`; `$payment->update(['status' => PaymentStatus::Abandoned])`; `activity()->performedOn($payment)->log('payment_abandoned')` with `adminReason`; `DB::afterCommit(fn () => PaymentAbandoned::dispatch($payment))`; return updated payment
- [ ] T020 [P] [US1] Create listener stub `app/Modules/Booking/Application/Listeners/ReleaseInventoryOnPaymentVoidedListener.php` — handles both `PaymentVoided` AND `PaymentAbandoned` events (register for both in `BookingServiceProvider`); `implements ShouldQueue`; `handle($event)`: load booking via `$event->payment->booking_id`; update all `service_inventory_reservations` for that booking to `status=released` where `status` is active/pending; update `bookings.payment_status` to `voided` or `abandoned` based on event type; no cross-module Eloquent model import — use Booking's own `BookingRepository`
- [ ] T021 [US1] Register listener in `app/Modules/Booking/Providers/BookingServiceProvider.php`: `Event::listen(PaymentVoided::class, [ReleaseInventoryOnPaymentVoidedListener::class, 'handle'])` and `Event::listen(PaymentAbandoned::class, [ReleaseInventoryOnPaymentVoidedListener::class, 'handle'])`
- [ ] T022 [US1] Add **Failed Payments** tab to `app/Modules/Payments/Filament/Pages/PaymentsOpsConsole.php` — create the Page class skeleton first if it doesn't exist: `extends Page`, navigation group "Payments", `$navigationIcon = 'heroicon-o-wrench-screwdriver'`, `$title = 'Payments Console'`; implement `InteractsWithTable` for this tab; query: `Payment::where('status', 'failed')->latest()`; columns: `public_id` copyable, `booking.public_id`, `amount_minor` money('EGP', divideBy:100), `failure_code` badge danger, `created_at` dateTime sortable; row actions: `RetryAction` (form modal with `reason` TextInput required, calls `RetryFailedPaymentAction`, then Notification success), `AbandonAction` (form modal with `reason` required, calls `MarkPaymentAbandonedAction`); both actions require `manage_payments` permission; `requiresConfirmation()` on Abandon; refresh table after each action
- [ ] T023 [P] [US1] Write `tests/Feature/Modules/Payments/RetryFailedPaymentTest.php` — test cases: `it('creates a new payment row and does not mutate the original failed row')`, `it('retry is blocked when payment status is not failed')`, `it('retry appends an audit log entry with the admin reason')`, `it('retry fires PaymentInitiated event after commit')`, `it('admin without manage_payments permission cannot access the Failed Payments tab')`; group: `->group('payments', 'failed-payments')`
- [ ] T024 [P] [US1] Write `tests/Feature/Modules/Payments/MarkPaymentAbandonedTest.php` — test cases: `it('transitions payment to abandoned and releases inventory reservations')`, `it('mark abandoned appends an audit log entry')`, `it('mark abandoned fires PaymentAbandoned event which is handled by Booking listener')`, `it('abandon is blocked when payment status is not failed')`; group: `->group('payments', 'failed-payments')`

**Checkpoint — US1 complete**: Admin sees failed payments in console; Retry and Abandon actions work; inventory is released on abandon; audit log captures both actions; Pest tests green.

---

## Phase 3: User Story 2 — Admin Captures or Voids a Stuck Authorization (Priority: P2)

**Goal**: Admin sees authorizations older than 24h and can manually capture (fires same commission flow as webhook) or void (releases inventory and held funds).

**Independent Test**: Seed a `payments` row with `status=authorized` and `created_at` 25h ago; verify Capture transitions to `captured` and fires `PaymentCaptured`; verify Void transitions to `voided` and fires `PaymentVoided`.

- [ ] T025 [US2] Create `app/Modules/Payments/Application/Actions/ManualCapturePaymentAction.php` — `execute(Payment $payment, string $adminReason): Payment`; guards: `throw_unless($payment->status === PaymentStatus::Authorized, ...)` inside `lockForUpdate()`; `DB::transaction`: call `$this->gateway->capture($payment->gateway_ref, $payment->amount_minor, $payment->amount_currency)`; on success: `$payment->update(['status' => Captured, 'captured_at' => now()])`; on gateway failure: throw `ManualCaptureGatewayException` — do NOT mutate payment; `activity()->performedOn($payment)->log('manual_capture')` with `adminReason`; `DB::afterCommit(fn () => PaymentCaptured::dispatch($payment))` — **reuses the existing `PaymentCaptured` event** so Settlement commission listener fires automatically; return updated payment
- [ ] T026 [US2] Create `app/Modules/Payments/Application/Actions/VoidStuckAuthorizationAction.php` — `execute(Payment $payment, string $adminReason): Payment`; guard: `throw_unless($payment->status === PaymentStatus::Authorized, ...)` inside `lockForUpdate()`; `DB::transaction`: call `$this->gateway->void($payment->gateway_ref)` (new method from T012); on success: `$payment->update(['status' => PaymentStatus::Voided])`; on gateway failure: throw `VoidGatewayException`; `activity()->performedOn($payment)->log('manual_void')` with `adminReason`; `DB::afterCommit(fn () => PaymentVoided::dispatch($payment))`; return updated payment
- [ ] T027 [US2] Add **Stuck Authorizations** tab to `app/Modules/Payments/Filament/Pages/PaymentsOpsConsole.php` — query: `Payment::where('status', 'authorized')->where('created_at', '<', now()->subHours(24))->latest('created_at')`; columns: `public_id` copyable, `booking.public_id`, `amount_minor` money('EGP', divideBy:100), `created_at` dateTime with description showing `diffForHumans()`; row actions: `CaptureAction` (form modal `reason` required, calls `ManualCapturePaymentAction`, success notification), `VoidAction` (form modal `reason` required + `requiresConfirmation()`, calls `VoidStuckAuthorizationAction`); both require `manage_payments` permission; show error notification if gateway throws
- [ ] T028 [P] [US2] Write `tests/Feature/Modules/Payments/ManualCaptureTest.php` — test cases: `it('manual capture transitions payment to captured and updates captured_at')`, `it('manual capture fires PaymentCaptured event triggering commission calculation')`, `it('manual capture appends audit log entry with reason')`, `it('capture is blocked when payment is not in authorized state')`, `it('two admins capturing the same payment concurrently results in one capture and one conflict error')`, `it('gateway failure during capture leaves payment in authorized state and throws exception')`; group: `->group('payments', 'manual-capture')`
- [ ] T029 [P] [US2] Write `tests/Feature/Modules/Payments/VoidAuthorizationTest.php` — test cases: `it('void transitions payment to voided and fires PaymentVoided event')`, `it('PaymentVoided listener releases service_inventory_reservations for the booking')`, `it('void appends audit log entry with reason')`, `it('void is blocked when payment is not in authorized state')`, `it('gateway failure during void leaves payment in authorized state')`; group: `->group('payments', 'void')`

**Checkpoint — US2 complete**: Admin sees stuck authorizations; Capture and Void work; `PaymentCaptured` reuse confirmed by commission test; `PaymentVoided` triggers inventory release; concurrency test green.

---

## Phase 4: User Story 3 — Admin Replays a Webhook Without Double-Charging (Priority: P3)

**Goal**: Admin picks an unprocessed `gateway_webhook_logs` row and re-dispatches it safely. Idempotency guarantees no double-charge on replay or double-replay.

**Independent Test**: Create a `gateway_webhook_logs` row with `processed_at=NULL`; trigger replay; assert `payments.status=captured` and `processed_at` set; trigger replay again; assert no new `payments` row and no duplicate ledger entry.

- [ ] T030 [US3] Create `app/Modules/Payments/Application/Actions/ReplayWebhookAction.php` — `execute(GatewayWebhookLog $log): void`; guard: `throw_unless($log->signature_valid, new \DomainException('cannot_replay_invalid_signature'))`; guard: `throw_unless(in_array($log->event_type, ['transaction_processed', 'transaction_response_callback']), new \DomainException('event_type_not_replayable'))`; `DB::transaction`: re-dispatch `$log->payload` through existing `ProcessPaymobWebhookAction::execute($log->payload)` — idempotency is guaranteed by `(gateway, gateway_ref) UNIQUE` constraint on `payments` table; `activity()->performedOn($log)->log('webhook_replayed')`; return void (ProcessPaymobWebhookAction fires its own events)
- [ ] T031 [US3] Add **Webhook Replay** tab to `app/Modules/Payments/Filament/Pages/PaymentsOpsConsole.php` — query: `GatewayWebhookLog::latest()`; filters: `SelectFilter` for `gateway`, `SelectFilter` for `event_type`, `TernaryFilter` for processed (processed_at IS NULL / NOT NULL / All); columns: `id`, `gateway` badge, `event_type` badge info, `signature_valid` IconColumn boolean, `processed_at` dateTime placeholder "Not processed", `created_at` dateTime; row action: `ReplayAction` — disabled with tooltip "Invalid signature" when `!signature_valid`; badge "Already processed" when `processed_at IS NOT NULL` but action still enabled (idempotent); calls `ReplayWebhookAction`, shows success notification with "Replay completed — idempotent" when already processed; requires `replay_webhooks` permission (separate from `manage_payments`)
- [ ] T032 [P] [US3] Write `tests/Feature/Modules/Payments/WebhookReplayIdempotencyTest.php` — test cases: `it('replays an unprocessed webhook and updates payment status to captured')`, `it('replaying the same log row twice produces identical final state with no duplicate payment rows')`, `it('replaying the same row twice produces no duplicate wallet_ledger entries')`, `it('replay is blocked for tampered webhook logs with signature_valid=false')`, `it('replay of an unrecognized event_type shows error and takes no action')`, `it('replay appends an audit log entry')`; group: `->group('payments', 'webhook-replay', 'idempotency')`

**Checkpoint — US3 complete**: Webhook Replay tab visible; replay of unprocessed row works; double-replay is idempotent; tampered webhooks are rejected; audit log confirms replay.

---

## Phase 5: User Story 4 — Admin Intakes and Tracks a Chargeback (Priority: P4)

**Goal**: Admin manually enters a chargeback case from bank/Paymob email notification. System creates the record, posts a wallet ledger reversal immediately, and tracks the case to resolution.

**Independent Test**: Call `OpenChargebackAction` with a captured payment; assert `payment_chargebacks` row exists with `status=open`; assert vendor's `wallet_ledger` has a new debit entry equal to the chargeback amount; call `ResolveChargebackAction` with `status=won`; assert wallet re-credit posted.

- [ ] T033 [US4] Create `app/Modules/Payments/Application/Actions/OpenChargebackAction.php` — `execute(OpenChargebackDto $dto): PaymentChargeback`; guards: `throw_unless($dto->payment->status === PaymentStatus::Captured, ...)`, `throw_unless($dto->amountMinor <= $dto->payment->amount_minor, ...)`, existing active chargeback check per plan §8.6; `DB::transaction`: insert `PaymentChargeback` with all DTO fields, `opened_at=now()`, `created_by=auth()->id()`; `activity()->performedOn($chargeback)->log('chargeback_opened')`; `DB::afterCommit(fn () => ChargebackOpened::dispatch($chargeback))`; return chargeback
- [ ] T034 [US4] Create `app/Modules/Payments/Application/Actions/ResolveChargebackAction.php` — `execute(ResolveChargebackDto $dto): PaymentChargeback`; guards: `throw_unless(in_array($dto->chargeback->status, [ChargebackStatus::Open, ChargebackStatus::UnderReview]), ...)` (block resolving an already-resolved case); `DB::transaction`: `$chargeback->update(['status' => $dto->status, 'resolved_at' => now(), 'admin_notes' => $dto->adminNotes, 'updated_by' => auth()->id()])`; `activity()->performedOn($chargeback)->log('chargeback_resolved')` with before/after status in properties; `DB::afterCommit(fn () => ChargebackResolved::dispatch($chargeback))`; return updated chargeback
- [ ] T035 [P] [US4] Create Settlement listener `app/Modules/Settlement/Application/Listeners/ReverseWalletCreditOnChargebackOpenedListener.php` — `implements ShouldQueue`; `handle(ChargebackOpened $event)`: load the booking from payment, identify vendor wallet; append a `wallet_ledger` debit entry (type: `chargeback_hold`, amount = `$event->chargeback->amount_minor`, currency = `$event->chargeback->amount_currency`); use Settlement's own `WalletRepository` — no direct Payments model import; no soft delete / no UPDATE to existing ledger rows (append-only per constitution Principle V)
- [ ] T036 [P] [US4] Create Settlement listener `app/Modules/Settlement/Application/Listeners/HandleChargebackResolvedListener.php` — `implements ShouldQueue`; `handle(ChargebackResolved $event)`: if `$event->chargeback->status === ChargebackStatus::Won` → append `wallet_ledger` credit entry (type: `chargeback_won`); if `Lost` → no-op (hold stands as permanent debit)
- [ ] T037 [US4] Register both Settlement listeners in `app/Modules/Settlement/Providers/SettlementServiceProvider.php` — `Event::listen(ChargebackOpened::class, [ReverseWalletCreditOnChargebackOpenedListener::class, 'handle'])` and `Event::listen(ChargebackResolved::class, [HandleChargebackResolvedListener::class, 'handle'])`
- [ ] T038 [US4] Add **Chargebacks** tab to `app/Modules/Payments/Filament/Pages/PaymentsOpsConsole.php` — query: `PaymentChargeback::with('payment')->latest('opened_at')`; filters: `SelectFilter` for `status`; columns per plan §10; header action: `IntakeChargebackAction` — form with `Select::make('payment_id')` (searchable by `public_id`, filtered to `status=captured`), `TextInput::make('gateway_case_id')` optional, `TextInput::make('amount_minor')` numeric required, translatable `reason` tabs (EN+AR) both required; calls `OpenChargebackAction` on submit; row action: `ResolveChargebackAction` (form: `Select` for status [won/lost], translatable `admin_notes` EN+AR tabs, both required); requires `manage_chargebacks` permission
- [ ] T039 [P] [US4] Write `tests/Feature/Modules/Payments/ChargebackTest.php` — test cases: `it('chargeback intake creates payment_chargebacks row with status=open')`, `it('ChargebackOpened listener posts a wallet_ledger debit for the vendor wallet')`, `it('chargeback resolution with status=won re-credits the vendor wallet')`, `it('chargeback resolution with status=lost does not re-credit the vendor wallet')`, `it('chargeback intake is blocked when disputed amount exceeds payment amount')`, `it('cannot open a second active chargeback for the same payment')`, `it('chargeback intake requires both en and ar reason fields')`, `it('already-resolved chargeback cannot be resolved again')`; group: `->group('payments', 'chargebacks')`

**Checkpoint — US4 complete**: Chargebacks tab visible; intake form creates record; `wallet_ledger` debit posted immediately; resolution conditionally re-credits; all validations enforced; Pest tests green.

---

## Phase 6: User Story 5 — Admin Monitors Gateway Health (Priority: P5)

**Goal**: A scheduled command runs every 5 minutes and records a health ping. The Gateway Health tab shows 24h success rate, average latency, and detected outage windows.

**Independent Test**: Seed `gateway_health_pings` rows with known success/failure patterns; verify success rate %, avg latency, and outage window detection match expected values; run `PingGatewayHealthCommand` in test and verify exactly one new row is inserted.

- [ ] T040 [US5] Create `app/Modules/Payments/Console/Commands/PingGatewayHealthCommand.php` — extends `Command`; `$signature = 'payments:ping-gateway-health'`; `handle()`: foreach active gateway code (Phase 1: `['paymob']`): start timer via `microtime(true)`; call `$this->gateway->ping()`; compute `latencyMs`; insert `GatewayHealthPing` row; log result to `Log::info()` or `Log::warning()` on failure; command NEVER throws — failed pings are data points; constructor injects `PaymentGateway`
- [ ] T041 [US5] Register schedule in `app/Modules/Payments/Providers/PaymentsServiceProvider.php` boot method — `$schedule->command(PingGatewayHealthCommand::class)->everyFiveMinutes()->withoutOverlapping()->runInBackground()`; ensure `Artisan::starting()` or `Commands::add()` registers the command in the artisan kernel
- [ ] T042 [US5] Add **Gateway Health** tab to `app/Modules/Payments/Filament/Pages/PaymentsOpsConsole.php` — this tab renders a custom Blade view or uses `ViewWidget`; queries: (a) `$pings = GatewayHealthPing::where('gateway_code', 'paymob')->where('checked_at', '>=', now()->subHours(24))->get()`; (b) compute `$successRate = $pings->where('success', true)->count() / max($pings->count(), 1) * 100`; (c) compute `$avgLatency = $pings->where('success', true)->avg('latency_ms') ?? 0`; (d) detect outage windows: group by consecutive `success=false` runs ordered by `checked_at`, flag any run of ≥3 as an outage window with start/end times; (e) if `$pings->count() === 0` show a danger `Notification`-style inline alert "Health monitoring not running"; render as `StatsOverviewWidget`-style with three `Stat` cards: Success Rate, Avg Latency, Active Outages; list outage windows below; no actions on this tab (read-only)
- [ ] T043 [P] [US5] Write `tests/Feature/Modules/Payments/GatewayHealthTest.php` — test cases: `it('PingGatewayHealthCommand inserts exactly one gateway_health_pings row per execution')`, `it('command inserts success=false row when gateway ping fails')`, `it('health tab correctly computes 100 percent success rate from all-success seed data')`, `it('health tab correctly computes success rate from mixed seed data')`, `it('health tab detects an outage window when 3 or more consecutive pings fail')`, `it('health tab shows monitoring-not-running alert when no pings exist in last 24h')`; group: `->group('payments', 'gateway-health')`

**Checkpoint — US5 complete**: `php artisan payments:ping-gateway-health` inserts one row; Gateway Health tab shows correct computed metrics; outage detection works; alert shown when no data; Pest tests green.

---

## Phase 7: User Story 6 — Admin Spot-Checks the Reconciliation Diff (Priority: P6)

**Goal**: Admin sees today's captured payment count from the gateway vs the platform record. Any mismatch is highlighted. A fallback is used when the gateway reporting API is unavailable.

**Independent Test**: Mock gateway `getTodayCapturedCount()` to return 101 when platform has 100 `captured` payments today; verify the diff tab reports "+1 at gateway" with a warning indicator.

- [ ] T044 [US6] Add **Reconciliation Diff** tab to `app/Modules/Payments/Filament/Pages/PaymentsOpsConsole.php` — this tab renders a custom view; platform count: `Payment::where('status', 'captured')->whereDate('captured_at', today())->count()`; gateway count: try `$this->gateway->getTodayCapturedCount()` catch `GatewayReportingUnavailableException` → fallback: `GatewayWebhookLog::where('event_type', 'transaction_processed')->where('signature_valid', true)->whereNotNull('processed_at')->whereDate('created_at', today())->count()`; compute diff `$gatewayCount - $platformCount`; render: if `$diff === 0` → green badge "No discrepancy"; if `$diff > 0` → amber badge "+{$diff} at gateway — check Webhook Replay tab"; if `$diff < 0` → red badge "{abs($diff)} phantom platform records — investigate immediately"; show `$platformCount` and `$gatewayCount` as labeled counters; include "Refresh" button (Livewire `$refresh` or `Action`); tab is read-only except for Refresh; loading state shown while query runs
- [ ] T045 [P] [US6] Write `tests/Feature/Modules/Payments/ReconciliationDiffTest.php` — test cases: `it('reconciliation diff returns zero and green indicator when gateway and platform counts match')`, `it('reconciliation diff highlights a discrepancy when gateway count exceeds platform count by 1')`, `it('reconciliation diff falls back to webhook log count when gateway reporting API throws GatewayReportingUnavailableException')`, `it('reconciliation diff shows red indicator when platform count exceeds gateway count')`, `it('refresh button re-runs the query')`; group: `->group('payments', 'reconciliation')`

**Checkpoint — US6 complete**: Reconciliation tab shows correct diff; fallback activates on gateway API failure; mismatch is highlighted with correct color coding; refresh works; Pest tests green.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Architecture tests, Shield permissions, final wiring, and phase validation.

- [ ] T046 [P] Write architecture test `tests/Architecture/ManualCaptureFiresSameEventAsWebhookTest.php` — assert `ManualCapturePaymentAction` dispatches `PaymentCaptured::class` (the same class dispatched by `CapturePaymentAction`); fail build if a distinct `ManualPaymentCaptured` event is introduced; use Pest Architecture `expect('App\Modules\Payments\Application\Actions\ManualCapturePaymentAction')->toUse(PaymentCaptured::class)`
- [ ] T047 [P] Write architecture test `tests/Architecture/GatewayInterfaceFullyImplementedTest.php` — assert `PaymobGateway` implements all methods declared in `PaymentGateway` interface including `void`, `ping`, `getTodayCapturedCount`; use PHP reflection or Pest Architecture helper to enumerate interface methods and assert each has a concrete implementation
- [ ] T048 [P] Extend `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` — add assertion that `GatewayHealthPing` model does not use `SoftDeletes` trait and has no `deleted_at` column in its migration
- [ ] T049 Run `php artisan shield:generate --all` — regenerates Shield permissions including `view_payments_ops_console`, `manage_payments`, `replay_webhooks`, `manage_chargebacks`; confirm permissions appear in `config/filament-shield.php` output
- [ ] T050 [P] Verify EN+AR translation key parity — run `LocaleParityTest` (existing architecture test) to confirm `lang/en/chargebacks.php` and `lang/ar/chargebacks.php` have identical key sets; fix any missing AR translations
- [ ] T051 Validate phase exit criteria against spec §"Phase Exit Criteria" — run full Pest suite: `./vendor/bin/pest --group=payments`; all 6 test files must be green; verify: (a) admin can retry/abandon failed payment, (b) manual capture + void work with audit trail, (c) webhook replay is idempotent, (d) chargeback intake creates wallet debit, (e) gateway health ping inserts rows, (f) reconciliation diff detects synthetic discrepancy
- [ ] T052 [P] Update `specs/021-payments-ops-console/checklists/requirements.md` — mark implementation checklist items as complete; note any cut-list items deferred to Phase 1.5

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: Starts after ADR-0019 accepted (T001 gate). T002–T003 migrations run first; T004–T016 all independent of each other [P]. T017 (ServiceProvider) runs last in Phase 1.
- **Phases 2–7 (User Stories)**: All depend on Phase 1 completion. Stories can proceed sequentially (solo developer) or in parallel once Phase 1 is done.
- **Phase 8 (Polish)**: Runs after all desired stories are complete.

### User Story Dependencies

```
Phase 1 (Foundation — all gates)
    ↓
Phase 2 (US1: Failed Payments)     ← can start first; no dependency on US2-US6
Phase 3 (US2: Stuck Auths)         ← independent of US1 (different actions, different tab)
Phase 4 (US3: Webhook Replay)      ← independent; ReplayWebhookAction uses ProcessPaymobWebhookAction from Phase 4.0
Phase 5 (US4: Chargebacks)         ← requires PaymentChargeback model from Phase 1; independent otherwise
Phase 6 (US5: Gateway Health)      ← requires PingResult/GatewayHealthPing from Phase 1; independent
Phase 7 (US6: Reconciliation Diff) ← requires getTodayCapturedCount from Phase 1; independent
    ↓
Phase 8 (Polish)
```

### Within Each Phase

- T002 (chargebacks migration) before T005 (PaymentChargeback model)
- T003 (health_pings migration) before T006 (GatewayHealthPing model)
- T011 (interface extension) before T012–T014 (gateway implementations)
- T001 (ADR) before T002–T003 (migrations) — constitution gate
- All [P]-marked tasks within a phase are file-independent and can run simultaneously

---

## Parallel Opportunities

### Phase 1 — all these can run simultaneously after T001+T002+T003:

```
T004 ChargebackStatus enum
T005 PaymentChargeback model       ← after T002
T006 GatewayHealthPing model       ← after T003
T007 OpenChargebackDto
T008 ResolveChargebackDto
T009 VoidResult DTO
T010 PingResult DTO
T012 PaymobGateway::void()         ← after T011
T013 PaymobGateway::ping()         ← after T011
T014 PaymobGateway::getTodayCapturedCount()  ← after T011
T015 Domain events (all 4 files)
T016 Lang files (en + ar)
```

### Phase 2 (US1) — tests can be written alongside implementation:

```
T018 RetryFailedPaymentAction
T019 MarkPaymentAbandonedAction     ← parallel to T018 (different file)
T020 ReleaseInventoryOnPaymentVoidedListener  ← parallel (different module)
T023 RetryFailedPaymentTest         ← write after T018
T024 MarkPaymentAbandonedTest       ← write after T019 (parallel to T023)
```

### Phases 3–7 — can run in parallel once Phase 1 is done:

```
Developer track A: Phase 3 (US2 — ManualCapture + Void)
Developer track B: Phase 4 (US3 — Webhook Replay)
Developer track C: Phase 5 (US4 — Chargebacks)
Developer track D: Phase 6 (US5 — Health Ping)
Developer track E: Phase 7 (US6 — Reconciliation)
```

---

## Implementation Strategy

### MVP First (US1 Only — Failed Payments Recovery)

1. Phase 1 (Setup — all tasks)
2. Phase 2 (US1 — RetryFailedPaymentAction + MarkPaymentAbandonedAction + Failed Payments tab)
3. **STOP and VALIDATE**: Run `./vendor/bin/pest --group=failed-payments`; demo Retry and Abandon in Filament
4. Deploy increment

### Incremental Delivery

1. Phase 1 → Foundation ready
2. Phase 2 → US1 ✅ Failed payments recovery (P1 — highest value)
3. Phase 3 → US2 ✅ Stuck authorizations + manual capture/void (P2)
4. Phase 4 → US3 ✅ Webhook replay (P3 — idempotency safety)
5. Phase 5 → US4 ✅ Chargeback intake + wallet reversal (P4)
6. Phase 6 → US5 ✅ Gateway health monitoring (P5)
7. Phase 7 → US6 ✅ Reconciliation diff (P6)
8. Phase 8 → Polish + architecture tests + shield + full Pest suite

### Solo Developer (recommended order)

Complete phases sequentially in listed order. Each checkpoint validates the partial console independently. Skip Phase 8 polish items marked [P] until all stories are complete.

---

## Notes

- `[P]` tasks = different files, no shared state, safe to run simultaneously
- `[Story]` label maps each task to a specific user story for traceability
- **ADR-0019 gate**: T001 must be marked `Accepted` by Ibrahim before ANY migration or code runs — this is non-negotiable per Constitution Principle VI
- **No new packages**: every package used is already in `docs/specs/10_Package_List.md`
- **No public API endpoints**: this feature is admin-only Filament; no changes to `api-registry.md`
- **Wallet ledger is append-only**: Settlement listeners (T035, T036) must INSERT new rows only — never UPDATE existing `wallet_ledger` entries
- **`PaymentCaptured` event reuse** (T025): `ManualCapturePaymentAction` MUST fire `PaymentCaptured` (same class as webhook capture), not a new event — architecture test T046 enforces this
- Commit after each phase checkpoint using conventional commit format: `feat(payments): add payments ops console — US1 failed payments recovery`
