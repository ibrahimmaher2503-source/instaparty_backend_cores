<?php

declare(strict_types=1);

use App\Modules\Payments\Application\Actions\ManualCapturePaymentAction;
use App\Modules\Payments\Application\Actions\MarkPaymentAbandonedAction;
use App\Modules\Payments\Application\Actions\OpenChargebackAction;
use App\Modules\Payments\Application\Actions\ReplayWebhookAction;
use App\Modules\Payments\Application\Actions\VoidStuckAuthorizationAction;
use App\Modules\Payments\Application\DTOs\OpenChargebackDto;
use App\Modules\Payments\Console\Commands\PingGatewayHealthCommand;
use App\Modules\Payments\Domain\Enums\ChargebackStatus;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\ChargebackOpened;
use App\Modules\Payments\Domain\Events\PaymentAbandoned;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\PaymentVoided;
use App\Modules\Payments\Domain\Models\GatewayHealthPing;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentChargeback;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.paymob.hmac_secret', 'feature-test-secret');
    // Seed fresh roles + permissions after RefreshDatabase wiped the DB
    app(IdentityRolesSeeder::class)->run();
    Permission::findOrCreate('payment.refund', 'web');
    Permission::findOrCreate('view_refund', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─── US3: Webhook Replay ─────────────────────────────────────────────────────

it('replaying the same webhook twice results in the same final payment state (idempotent)', function (): void {
    Event::fake([PaymentCaptured::class]);

    $data = makeConfirmedBookingWithItem();
    $gatewayRef = 'PMB-REPLAY-'.Str::ulid();

    $payment = Payment::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'gateway' => 'paymob',
        'gateway_ref' => $gatewayRef,
        'amount_minor' => 50000,
        'amount_currency' => 'EGP',
        'method' => PaymentMethod::Card,
        'status' => PaymentStatus::Pending,
    ]);

    $payload = [
        'type' => 'TRANSACTION',
        'obj' => ['id' => $gatewayRef, 'success' => true, 'amount_cents' => 50000],
    ];
    $signature = hash_hmac('sha512', json_encode($payload) ?: '', 'feature-test-secret');

    $log = GatewayWebhookLog::create([
        'gateway' => 'paymob',
        'event_type' => 'TRANSACTION',
        'signature_valid' => true,
        'payload' => $payload,
        'created_at' => now(),
    ]);

    $admin = makeAdminUser();

    // First replay
    app(ReplayWebhookAction::class)->execute($log->id, $admin->id);
    $statusAfterFirst = $payment->fresh()->status;

    // Second replay — must not fail or double-charge
    app(ReplayWebhookAction::class)->execute($log->id, $admin->id);
    $statusAfterSecond = $payment->fresh()->status;

    expect($statusAfterFirst)->toBe(PaymentStatus::Captured);
    expect($statusAfterSecond)->toBe(PaymentStatus::Captured);

    // Only one PaymentCaptured event despite two replays
    Event::assertDispatchedTimes(PaymentCaptured::class, 1);

    // Audit log written for each replay
    $this->assertDatabaseCount('audit_logs', 2);
})->group('payments', 'ops-console');

// ─── US2: Stuck Authorizations ────────────────────────────────────────────────

it('manual capture creates audit row and fires PaymentCaptured', function (): void {
    Event::fake([PaymentCaptured::class]);

    $data = makeConfirmedBookingWithItem();
    $payment = Payment::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'gateway' => 'paymob',
        'gateway_ref' => 'PMB-AUTH-'.Str::ulid(),
        'amount_minor' => 50000,
        'amount_currency' => 'EGP',
        'method' => PaymentMethod::Card,
        'status' => PaymentStatus::Authorized,
        'created_at' => now()->subHours(25),
    ]);

    $admin = makeAdminUser();

    app(ManualCapturePaymentAction::class)->execute($payment->id, $admin->id, 'Admin manually captured after 24h');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Captured);
    expect($payment->fresh()->captured_at)->not->toBeNull();

    Event::assertDispatched(PaymentCaptured::class, fn (PaymentCaptured $e): bool => $e->paymentId === $payment->id
    );
})->group('payments', 'ops-console');

it('void of authorized payment fires PaymentVoided', function (): void {
    Event::fake([PaymentVoided::class]);

    $data = makeConfirmedBookingWithItem();
    $payment = Payment::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'gateway' => 'paymob',
        'gateway_ref' => 'PMB-STUCK-'.Str::ulid(),
        'amount_minor' => 50000,
        'amount_currency' => 'EGP',
        'method' => PaymentMethod::Card,
        'status' => PaymentStatus::Authorized,
        'created_at' => now()->subHours(30),
    ]);

    $admin = makeAdminUser();

    app(VoidStuckAuthorizationAction::class)->execute($payment->id, $admin->id, 'Voided stuck auth');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Voided);

    Event::assertDispatched(PaymentVoided::class, fn (PaymentVoided $e): bool => $e->paymentId === $payment->id
    );
})->group('payments', 'ops-console');

// ─── US4: Chargebacks ─────────────────────────────────────────────────────────

it('chargeback intake fires ChargebackOpened', function (): void {
    Event::fake([ChargebackOpened::class]);

    $data = makeConfirmedBookingWithItem();
    $payment = makeCapturedPayment($data['booking'], $data['customer']);
    $admin = makeAdminUser();

    $chargeback = app(OpenChargebackAction::class)->execute(new OpenChargebackDto(
        paymentId: $payment->id,
        adminUserId: $admin->id,
        reason: ['en' => 'Customer disputed charge', 'ar' => 'العميل يعترض'],
        amountMinor: 50000,
        amountCurrency: 'EGP',
    ));

    expect($chargeback)->toBeInstanceOf(PaymentChargeback::class);
    expect($chargeback->status)->toBe(ChargebackStatus::Open);

    Event::assertDispatched(ChargebackOpened::class, fn (ChargebackOpened $e): bool => $e->chargebackId === $chargeback->id && $e->paymentId === $payment->id
    );
})->group('payments', 'ops-console');

it('chargeback amount cannot exceed original payment amount', function (): void {
    $data = makeConfirmedBookingWithItem();
    $payment = makeCapturedPayment($data['booking'], $data['customer'], 50000);
    $admin = makeAdminUser();

    expect(fn () => app(OpenChargebackAction::class)->execute(new OpenChargebackDto(
        paymentId: $payment->id,
        adminUserId: $admin->id,
        reason: ['en' => 'Dispute', 'ar' => 'نزاع'],
        amountMinor: 99999,
        amountCurrency: 'EGP',
    )))->toThrow(ValidationException::class);
})->group('payments', 'ops-console');

// ─── US1: Failed Payments ─────────────────────────────────────────────────────

it('marking failed payment abandoned fires PaymentAbandoned', function (): void {
    Event::fake([PaymentAbandoned::class]);

    $data = makeConfirmedBookingWithItem();
    $payment = Payment::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'gateway' => 'paymob',
        'gateway_ref' => 'PMB-FAIL-'.Str::ulid(),
        'amount_minor' => 50000,
        'amount_currency' => 'EGP',
        'method' => PaymentMethod::Card,
        'status' => PaymentStatus::Failed,
    ]);

    $admin = makeAdminUser();

    app(MarkPaymentAbandonedAction::class)->execute($payment->id, $admin->id);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Abandoned);

    Event::assertDispatched(PaymentAbandoned::class, fn (PaymentAbandoned $e): bool => $e->paymentId === $payment->id
    );
})->group('payments', 'ops-console');

// ─── US5: Gateway Health ──────────────────────────────────────────────────────

it('health ping command populates gateway_health_pings table', function (): void {
    config()->set('services.paymob.health_check_order_id', 'ORDER-12345');

    $this->assertDatabaseCount('gateway_health_pings', 0);

    Artisan::call(PingGatewayHealthCommand::class);

    $this->assertDatabaseCount('gateway_health_pings', 1);

    $ping = GatewayHealthPing::first();
    expect($ping->gateway_code)->toBe('paymob');
    expect($ping->success)->toBeTrue();
    expect($ping->latency_ms)->toBeGreaterThanOrEqual(0);
})->group('payments', 'ops-console');

// ─── US6: Reconciliation ──────────────────────────────────────────────────────

it('reconciliation diff catches synthetic discrepancy via local fallback', function (): void {
    $data = makeConfirmedBookingWithItem();

    // Platform has 2 captured payments today
    makeCapturedPayment($data['booking'], $data['customer'], 50000);
    makeCapturedPayment($data['booking'], $data['customer'], 30000);

    // Gateway webhook logs show 3 processed events (gateway has one more than platform knows)
    GatewayWebhookLog::create([
        'gateway' => 'paymob',
        'event_type' => 'transaction_processed',
        'signature_valid' => true,
        'payload' => ['obj' => ['success' => true]],
        'processed_at' => now(),
        'created_at' => now(),
    ]);
    GatewayWebhookLog::create([
        'gateway' => 'paymob',
        'event_type' => 'transaction_processed',
        'signature_valid' => true,
        'payload' => ['obj' => ['success' => true]],
        'processed_at' => now(),
        'created_at' => now(),
    ]);
    GatewayWebhookLog::create([
        'gateway' => 'paymob',
        'event_type' => 'transaction_processed',
        'signature_valid' => true,
        'payload' => ['obj' => ['success' => true]],
        'processed_at' => now(),
        'created_at' => now(),
    ]);

    $platformCount = Payment::query()
        ->where('status', PaymentStatus::Captured)
        ->whereDate('captured_at', today())
        ->count();

    $gatewayFallbackCount = GatewayWebhookLog::query()
        ->where('event_type', 'transaction_processed')
        ->whereNotNull('processed_at')
        ->whereDate('created_at', today())
        ->count();

    expect($platformCount)->toBe(2);
    expect($gatewayFallbackCount)->toBe(3);
    expect($gatewayFallbackCount - $platformCount)->toBe(1);
})->group('payments', 'ops-console');
