<?php

declare(strict_types=1);

use App\Modules\Payments\Infrastructure\Support\MapPaymobFailureCode;

it('maps "Insufficient funds" to insufficient_funds', function (): void {
    expect(MapPaymobFailureCode::fromMessage('Insufficient funds'))->toBe('insufficient_funds');
});

it('maps "DECLINED by issuer" case-insensitively to declined_by_issuer', function (): void {
    expect(MapPaymobFailureCode::fromMessage('DECLINED by issuer'))->toBe('declined_by_issuer');
});

it('maps "Card has expired" to expired_card', function (): void {
    expect(MapPaymobFailureCode::fromMessage('Card has expired'))->toBe('expired_card');
});

it('maps "fraud suspected" to fraud_suspected', function (): void {
    expect(MapPaymobFailureCode::fromMessage('Fraud suspected on this card'))->toBe('fraud_suspected');
});

it('returns unknown for null input', function (): void {
    expect(MapPaymobFailureCode::fromMessage(null))->toBe('unknown');
});

it('returns unknown for unrecognised messages', function (): void {
    expect(MapPaymobFailureCode::fromMessage('Some unrelated error from the gateway'))->toBe('unknown');
});
