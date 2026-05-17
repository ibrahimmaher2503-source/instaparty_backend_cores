<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\CapturedState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.paymob.hmac_secret', 'webhook-idempotency-test-secret');
});

function makeWebhookPayment(): Payment
{
    $data = makeConfirmedBookingWithItem();

    return Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $data['booking']->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-DUP-' . Str::ulid(),
        'amount_minor'    => 75000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Pending,
    ]);
}

function signTestWebhook(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

    return hash_hmac('sha512', $json, (string) config('services.paymob.hmac_secret'));
}

it('delivers the same webhook 10 times sequentially and produces exactly one capture ledger group', function (): void {
    $payment = makeWebhookPayment();
    $payload = [
        'type' => 'TRANSACTION',
        'obj'  => [
            'id'           => $payment->gateway_ref,
            'order'        => ['id' => 'ORDER-' . $payment->id],
            'success'      => true,
            'amount_cents' => 75000,
        ],
    ];

    for ($i = 0; $i < 10; $i++) {
        $this->withHeaders(['HMAC' => signTestWebhook($payload), 'Content-Type' => 'application/json'])
            ->postJson('/api/webhooks/paymob', $payload);
    }

    // Exactly one payment_capture ledger group
    $groupCount = DB::table('ledger_transaction_groups')
        ->where('kind', 'payment_capture')
        ->count();
    expect($groupCount)->toBe(1);

    // Payment is captured exactly once
    $payment->refresh();
    expect($payment->status)->toBeInstanceOf(CapturedState::class);

    // Exactly two ledger entries (debit + credit) in the single group
    $group = DB::table('ledger_transaction_groups')->where('kind', 'payment_capture')->first();
    expect(DB::table('wallet_ledger')->where('transaction_group_id', $group->id)->count())->toBe(2);
})->group('idempotency', 'us2');

it('late duplicate webhook after a week is a no-op', function (): void {
    $payment = makeWebhookPayment();
    $payload = [
        'type' => 'TRANSACTION',
        'obj'  => [
            'id'           => $payment->gateway_ref,
            'order'        => ['id' => 'ORDER-LATE-' . $payment->id],
            'success'      => true,
            'amount_cents' => 75000,
        ],
    ];

    // First delivery — captures the payment
    $this->withHeaders(['HMAC' => signTestWebhook($payload), 'Content-Type' => 'application/json'])
        ->postJson('/api/webhooks/paymob', $payload);

    $groupsBefore = DB::table('ledger_transaction_groups')->where('kind', 'payment_capture')->count();

    // Simulate a week later — idempotency window (30d) still covers this
    $this->withHeaders(['HMAC' => signTestWebhook($payload), 'Content-Type' => 'application/json'])
        ->postJson('/api/webhooks/paymob', $payload);

    $groupsAfter = DB::table('ledger_transaction_groups')->where('kind', 'payment_capture')->count();

    expect($groupsAfter)->toBe($groupsBefore);
})->group('idempotency', 'us2');
