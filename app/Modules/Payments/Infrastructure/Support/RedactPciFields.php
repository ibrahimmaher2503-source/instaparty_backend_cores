<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Support;

class RedactPciFields
{
    private const FORBIDDEN = ['pan', 'cvv', 'card_number', 'expiry', 'exp_month', 'exp_year', 'token'];

    public static function redact(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN, true)) {
                $result[$key] = '[REDACTED]';
                continue;
            }

            $result[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $result;
    }
}
