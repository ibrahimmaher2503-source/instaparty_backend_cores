<?php

declare(strict_types=1);

use App\Modules\Payments\Infrastructure\Gateways\PaymobGateway;

beforeEach(function (): void {
    config()->set('services.paymob.hmac_secret', 'unit-test-secret');
});

function signPayload(array $payload): string
{
    $secret = (string) config('services.paymob.hmac_secret');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

    return hash_hmac('sha512', $json, $secret);
}

it('accepts a valid HMAC signature', function (): void {
    $payload = ['type' => 'TRANSACTION', 'obj' => ['id' => 12345, 'success' => true, 'amount_cents' => 50000]];
    $signature = signPayload($payload);

    expect((new PaymobGateway)->verifyWebhookSignature($payload, $signature))->toBeTrue();
});

it('rejects a single-byte tampered HMAC', function (): void {
    $payload = ['type' => 'TRANSACTION', 'obj' => ['id' => 12345, 'success' => true]];
    $signature = signPayload($payload);
    $tampered = substr($signature, 0, -1).(($signature[strlen($signature) - 1] === '0') ? '1' : '0');

    expect((new PaymobGateway)->verifyWebhookSignature($payload, $tampered))->toBeFalse();
});

it('rejects a missing HMAC (empty string)', function (): void {
    $payload = ['type' => 'TRANSACTION', 'obj' => ['id' => 12345]];

    expect((new PaymobGateway)->verifyWebhookSignature($payload, ''))->toBeFalse();
});

it('rejects an HMAC computed with a different secret', function (): void {
    $payload = ['obj' => ['id' => 99]];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    $wrongSecretSig = hash_hmac('sha512', $json, 'wrong-secret');

    expect((new PaymobGateway)->verifyWebhookSignature($payload, $wrongSecretSig))->toBeFalse();
});

it('rejects an HMAC over a reordered payload (field order matters)', function (): void {
    $payloadA = ['a' => 1, 'b' => 2, 'c' => 3];
    $payloadB = ['c' => 3, 'b' => 2, 'a' => 1];
    $sigA = signPayload($payloadA);

    expect((new PaymobGateway)->verifyWebhookSignature($payloadB, $sigA))->toBeFalse();
});
