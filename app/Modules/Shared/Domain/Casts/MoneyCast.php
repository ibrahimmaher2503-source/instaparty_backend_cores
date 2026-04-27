<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Casts;

use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * @implements CastsAttributes<Money, Money|int>
 */
class MoneyCast implements CastsAttributes
{
    public function __construct(private readonly string $field) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): Money
    {
        $minor = $attributes["{$this->field}_minor"] ?? null;
        $currency = $attributes["{$this->field}_currency"] ?? null;

        if ($minor === null && $currency === null) {
            throw new UnexpectedValueException(
                "Money columns {$this->field}_minor and {$this->field}_currency are both null — check migration."
            );
        }

        return Money::ofMinor((int) $minor, (string) $currency);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_float($value)) {
            throw new InvalidArgumentException(
                'Float values are forbidden for money. Use Money::ofMinor() or pass an integer (minor units).'
            );
        }

        if ($value instanceof Money) {
            return [
                "{$this->field}_minor"    => $value->getMinorAmount()->toInt(),
                "{$this->field}_currency" => $value->getCurrency()->getCurrencyCode(),
            ];
        }

        if (is_int($value)) {
            $currency = $attributes["{$this->field}_currency"] ?? null;

            if ($currency === null) {
                throw new InvalidArgumentException(
                    "Cannot set money from int: {$this->field}_currency has no value on this model."
                );
            }

            return [
                "{$this->field}_minor"    => $value,
                "{$this->field}_currency" => $currency,
            ];
        }

        throw new InvalidArgumentException(
            'MoneyCast::set() accepts Money instances or int (minor units) only.'
        );
    }
}
