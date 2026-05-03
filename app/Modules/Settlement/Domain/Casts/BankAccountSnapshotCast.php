<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Casts;

use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<BankAccountSnapshot, BankAccountSnapshot|array<string, mixed>|string>
 */
class BankAccountSnapshotCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BankAccountSnapshot
    {
        if ($value === null) {
            return null;
        }

        $data = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($data)) {
            throw new InvalidArgumentException(
                "BankAccountSnapshotCast: expected JSON array, got: {$value}"
            );
        }

        return BankAccountSnapshot::fromArray($data);
    }

    /** @param BankAccountSnapshot|array<string, mixed>|string|null $value */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof BankAccountSnapshot) {
            return json_encode($value->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if (is_string($value)) {
            // Assume already-serialized JSON; validate round-trip
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(
                    'BankAccountSnapshotCast: invalid JSON string provided.'
                );
            }

            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        throw new InvalidArgumentException(
            'BankAccountSnapshotCast::set() accepts BankAccountSnapshot, array, or JSON string only.'
        );
    }
}
