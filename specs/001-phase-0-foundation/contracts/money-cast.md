# Contract: MoneyCast

**Class**: `App\Modules\Shared\Domain\Casts\MoneyCast`
**Implements**: `Illuminate\Contracts\Database\Eloquent\CastsAttributes`

---

## Purpose

Convert a pair of DB columns (`{field}_minor` BIGINT + `{field}_currency` CHAR(3)) to and from a `Brick\Money\Money` value object. Enforces the project-wide rule: **no floats for money**.

---

## Interface

### Declaration on a model

```php
protected function casts(): array
{
    return [
        // key = column name prefix (without _minor/_currency suffix)
        'base_price' => MoneyCast::class . ':base_price',
        'security_deposit' => MoneyCast::class . ':security_deposit',
    ];
}
```

### `get(Model $model, string $key, mixed $value, array $attributes): Money`

- Reads `$attributes["{$key}_minor"]` (int) and `$attributes["{$key}_currency"]` (string).
- Returns `Brick\Money\Money::ofMinor($minor, $currency)`.
- Throws `UnexpectedValueException` if `_minor` is null and `_currency` is null (indicates misconfigured migration).

### `set(Model $model, string $key, mixed $value, array $attributes): array`

- Accepts `Brick\Money\Money` or `int` (integer = minor units; currency resolved from `$attributes["{$key}_currency"]`).
- Returns `["{$key}_minor" => $minorInt, "{$key}_currency" => $currencyCode]`.
- Throws `InvalidArgumentException` if `$value` is `float` (never allow float money).
- Throws `InvalidArgumentException` if `$value` is `int` and no currency code can be resolved.

---

## Usage examples

```php
// Reading:
$service->base_price;
// => Brick\Money\Money { amount: 5000, currency: EGP }

$service->base_price->getAmount()->toFloat(); // => 50.0 (EGP)
$service->base_price->formatTo('en');         // => "EGP 50.00"
$service->base_price->getMinorAmount()->toInt(); // => 5000

// Writing (preferred):
$service->base_price = Money::ofMinor(7500, 'EGP');

// Writing (integer shorthand — requires currency already set on model):
$service->base_price = 7500; // currency resolved from existing column value

// Prohibited — throws InvalidArgumentException:
$service->base_price = 75.00; // NEVER USE FLOATS
```

---

## Rules

1. NEVER store `float` — only `int` minor units.
2. The currency code MUST be an ISO 4217 code (`'EGP'`, `'USD'`, etc.).
3. Always use `Money::ofMinor()` to construct; never `Money::of()` with a decimal.
4. In Filament Resources, display money via `->money('EGP', divideBy: 100)` on `TextColumn` — never display the raw `_minor` value.
5. In API Resources, convert to display string using `$money->formatTo(app()->getLocale())`.
