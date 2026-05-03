<?php

declare(strict_types=1);

use App\Modules\Payments\Infrastructure\Support\RedactPciFields;

it('replaces top-level forbidden keys with [REDACTED]', function (): void {
    $out = RedactPciFields::redact([
        'pan' => '4111111111111111',
        'cvv' => '123',
        'card_number' => '4111-1111-1111-1111',
        'token' => 'tok_secret',
        'amount_cents' => 50000,
    ]);

    expect($out['pan'])->toBe('[REDACTED]');
    expect($out['cvv'])->toBe('[REDACTED]');
    expect($out['card_number'])->toBe('[REDACTED]');
    expect($out['token'])->toBe('[REDACTED]');
    expect($out['amount_cents'])->toBe(50000);
});

it('redacts nested forbidden keys recursively', function (): void {
    $out = RedactPciFields::redact([
        'source_data' => [
            'pan' => '4111111111111111',
            'sub_type' => 'card',
            'nested' => [
                'cvv' => '123',
                'safe_field' => 'ok',
            ],
        ],
    ]);

    expect($out['source_data']['pan'])->toBe('[REDACTED]');
    expect($out['source_data']['sub_type'])->toBe('card');
    expect($out['source_data']['nested']['cvv'])->toBe('[REDACTED]');
    expect($out['source_data']['nested']['safe_field'])->toBe('ok');
});

it('matches forbidden keys case-insensitively', function (): void {
    $out = RedactPciFields::redact(['PAN' => 'x', 'CVV' => 'y']);

    expect($out['PAN'])->toBe('[REDACTED]');
    expect($out['CVV'])->toBe('[REDACTED]');
});

it('preserves arrays of scalars on non-forbidden keys', function (): void {
    $out = RedactPciFields::redact(['tags' => ['a', 'b', 'c']]);

    expect($out['tags'])->toBe(['a', 'b', 'c']);
});
