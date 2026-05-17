<?php

declare(strict_types=1);

use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('throws DuplicateIdempotencyKeyWithDifferentPayloadException when same key used with different payload', function (): void {
    $service = app(IdempotencyService::class);

    $key     = 'conflict-test-' . uniqid();
    $payload = ['amount' => 100, 'currency' => 'EGP'];

    // First call succeeds
    $service->remember('internal_action', $key, $payload, fn () => 'first_result');

    // Second call with different payload should throw
    $differentPayload = ['amount' => 200, 'currency' => 'EGP'];

    expect(fn () => $service->remember('internal_action', $key, $differentPayload, fn () => 'second_result'))
        ->toThrow(DuplicateIdempotencyKeyWithDifferentPayloadException::class);
})->group('idempotency', 'us2');

it('returns stored result when same key and same payload replayed', function (): void {
    $service = app(IdempotencyService::class);

    $key     = 'replay-test-' . uniqid();
    $payload = ['amount' => 500, 'currency' => 'EGP'];

    $first  = $service->remember('internal_action', $key, $payload, fn () => 'original_value');
    $second = $service->remember('internal_action', $key, $payload, fn () => 'should_not_run');

    expect($first)->toBe('original_value');
    expect($second)->toBe('original_value');
})->group('idempotency', 'us2');

it('scopes idempotency keys independently per scope', function (): void {
    $service = app(IdempotencyService::class);

    $key     = 'scope-test-' . uniqid();
    $payload = ['type' => 'test'];

    $httpResult     = $service->remember('http', $key, $payload, fn () => 'http_result');
    $webhookResult  = $service->remember('internal_webhook', $key, $payload, fn () => 'webhook_result');
    $actionResult   = $service->remember('internal_action', $key, $payload, fn () => 'action_result');

    // Each scope is independent — no cross-scope collision
    expect($httpResult)->toBe('http_result');
    expect($webhookResult)->toBe('webhook_result');
    expect($actionResult)->toBe('action_result');
})->group('idempotency', 'us2');
