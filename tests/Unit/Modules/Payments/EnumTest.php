<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;

it('payment status from captured works', function (): void {
    expect(PaymentStatus::from('captured'))->toBe(PaymentStatus::Captured);
});

it('refund reason values are lowercase strings', function (): void {
    expect(RefundReasonCode::values())->toHaveCount(5)->toContain('customer_request');
});
