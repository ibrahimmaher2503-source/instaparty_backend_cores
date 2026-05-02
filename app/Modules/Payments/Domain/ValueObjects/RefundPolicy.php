<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\ValueObjects;

final readonly class RefundPolicy
{
    public function __construct(
        public bool $allowed,
        public string $reasonCode,
        public string $reasonMessageKey,
    ) {}

    public static function allowed(): self
    {
        return new self(true, 'allowed', 'refunds.policy.allowed');
    }

    public static function denied(string $code, string $key): self
    {
        return new self(false, $code, $key);
    }
}
