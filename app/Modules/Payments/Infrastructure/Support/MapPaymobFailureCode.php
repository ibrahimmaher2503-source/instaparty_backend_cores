<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Support;

class MapPaymobFailureCode
{
    public static function fromMessage(?string $message): string
    {
        $m = strtolower((string) $message);

        return match (true) {
            str_contains($m, 'insufficient') => 'insufficient_funds',
            str_contains($m, 'declined') => 'declined_by_issuer',
            str_contains($m, 'expired') => 'expired_card',
            str_contains($m, 'fraud') => 'fraud_suspected',
            default => 'unknown',
        };
    }
}
