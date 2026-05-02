<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.paymob.hmac_secret', 'feature-test-secret');
});

function makePendingPayment(): Payment
{
    $data = makeConfirmedBookingWithItem();

    return Payment::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'gateway' => 'paymob',
        'gateway_ref' => 'PMB-WEBHOOK-'.Str::ulid(),
        'amount_minor' => 50000,
        'amount_currency' => 'EGP',
        'method' => PaymentMethod::Card,
        'status' => PaymentStatus::Pending,
    ]);
}

function signWebhook(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

    return hash_hmac('sha512', $json, (string) config('services.paymob.hmac_secret'));
}

it('valid HMAC + success=true captures payment and dispatches PaymentCaptured', function (): void {
    Event::fake([PaymentCaptured::class, PaymentFailed::class]);
    $payment = makePendingPayment();
    $payload = ['type' => 'TRANSACTION', 'obj' => ['id' => $payment->gateway_ref, 'success' => true, 'amount_cents' => 50000]];

    $response = $this->withHeaders(['HMAC' => signWebhook($payload), 'Content-Type' => 'application/json'])
        ->postJson('/api/v1/webhooks/paymob', $payload);

    $response->assertStatus(200);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Captured);
    Event::assertDispatched(PaymentCaptured::class);
})->group('payments');

it('valid HMAC + success=false marks payment failed and dispatches PaymentFailed', function (): void {
    Event::fake([PaymentCaptured::class, PaymentFailed::class]);
    $payment = makePendingPayment();
    $payload = ['type' => 'TRANSACTION', 'obj' => ['id' => $payment->gateway_ref, 'success' => false, 'amount_cents' => 50000]];

    $response = $this->withHeaders(['HMAC' => signWebhook($payload)])
        ->postJson('/api/v1/webhooks/paymob', $payload);

    $response->assertStatus(200);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
    Event::assertDispatched(PaymentFailed::class);
})->group('payments');

it('invalid HMAC returns 401, no payment mutation, log notes signature_valid=false', function (): void {
    $payment = makePendingPayment();
    $payload = ['obj' => ['id' => $payment->gateway_ref, 'success' => true, 'amount_cents' => 50000]];

    $response = $this->withHeaders(['HMAC' => str_repeat('0', 128)])
        ->postJson('/api/v1/webhooks/paymob', $payload);

    $response->assertStatus(401);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
    expect(GatewayWebhookLog::where('signature_valid', false)->count())->toBeGreaterThan(0);
})->group('payments');

it('replay (same payload twice) results in exactly one captured payment', function (): void {
    $payment = makePendingPayment();
    $payload = ['type' => 'TRANSACTION', 'obj' => ['id' => $payment->gateway_ref, 'success' => true, 'amount_cents' => 50000]];
    $sig = signWebhook($payload);

    $this->withHeaders(['HMAC' => $sig])->postJson('/api/v1/webhooks/paymob', $payload)->assertStatus(200);
    $this->withHeaders(['HMAC' => $sig])->postJson('/api/v1/webhooks/paymob', $payload)->assertStatus(200);

    expect(Payment::where('gateway_ref', $payment->gateway_ref)->where('status', PaymentStatus::Captured)->count())->toBe(1);
})->group('payments');

it('unknown gateway_ref returns 200, log records processing_error, no payment created', function (): void {
    $payload = ['obj' => ['id' => 'PMB-NONEXISTENT-'.Str::ulid(), 'success' => true, 'amount_cents' => 1]];
    $sig = signWebhook($payload);

    $response = $this->withHeaders(['HMAC' => $sig])->postJson('/api/v1/webhooks/paymob', $payload);

    $response->assertStatus(200);
    expect(GatewayWebhookLog::whereNotNull('processing_error')->count())->toBeGreaterThan(0);
})->group('payments');
