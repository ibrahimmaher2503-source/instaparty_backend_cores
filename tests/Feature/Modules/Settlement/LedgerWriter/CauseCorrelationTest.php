<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('all ledger entries written during a webhook handler share the same correlation_id', function (): void {
    config()->set('services.paymob.hmac_secret', 'cause-corr-test-secret');

    $data    = makeConfirmedBookingWithItem();
    $payment = Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $data['booking']->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-CORR-' . Str::ulid(),
        'amount_minor'    => 60_000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Pending,
    ]);

    $payload   = [
        'type' => 'TRANSACTION',
        'obj'  => [
            'id'           => $payment->gateway_ref,
            'order'        => ['id' => 'ORDER-CORR-' . $payment->id],
            'success'      => true,
            'amount_cents' => 60_000,
        ],
    ];
    $signature = hash_hmac(
        'sha512',
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        'cause-corr-test-secret'
    );

    $this->withHeaders(['HMAC' => $signature, 'Content-Type' => 'application/json'])
        ->postJson('/api/webhooks/paymob', $payload);

    // All wallet_ledger rows created during this webhook must share one correlation_id
    $correlationIds = DB::table('wallet_ledger')
        ->whereNotNull('correlation_id')
        ->pluck('correlation_id')
        ->unique();

    expect($correlationIds)->toHaveCount(1);

    // The payment row should also carry the same correlation_id
    $payment->refresh();
    expect($payment->correlation_id)->toEqual($correlationIds->first());
})->group('us5');

it('correlation_id on the payment row matches the one on its capture ledger group', function (): void {
    config()->set('services.paymob.hmac_secret', 'cause-corr-test-secret2');

    $data    = makeConfirmedBookingWithItem();
    $payment = Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $data['booking']->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-CORR2-' . Str::ulid(),
        'amount_minor'    => 35_000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Pending,
    ]);

    $payload   = [
        'type' => 'TRANSACTION',
        'obj'  => [
            'id'           => $payment->gateway_ref,
            'order'        => ['id' => 'ORDER-CORR2-' . $payment->id],
            'success'      => true,
            'amount_cents' => 35_000,
        ],
    ];
    $signature = hash_hmac(
        'sha512',
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        'cause-corr-test-secret2'
    );

    $this->withHeaders(['HMAC' => $signature, 'Content-Type' => 'application/json'])
        ->postJson('/api/webhooks/paymob', $payload);

    $payment->refresh();
    $captureGroup = DB::table('ledger_transaction_groups')
        ->where('id', $payment->capture_ledger_group_id)
        ->first();

    expect($captureGroup)->not()->toBeNull();
    expect($payment->correlation_id)->toEqual($captureGroup->correlation_id);
})->group('us5');
