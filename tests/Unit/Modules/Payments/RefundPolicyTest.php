<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\ValueObjects\RefundPolicy;

it('creates allowed policy', function (): void {
    expect(RefundPolicy::allowed()->allowed)->toBeTrue();
});

it('creates denied policy', function (): void {
    $policy = RefundPolicy::denied('x', 'y');
    expect($policy->reasonCode)->toBe('x');
});
