---
description: Rules for Action classes — fat single-purpose application services
globs:
  - "app/Modules/*/Application/Actions/*.php"
---

# Action Class Rules

## Shape

- One public method: `execute(...)`. Multiple public methods are forbidden.
- Constructor injection for all dependencies.
- Return either a DTO, a model, or `void`. Never an array.
- Accept either typed primitives, a DTO, or a Form Request — never a raw `Request`.

## Transactions

- Wrap every mutation in `DB::transaction(fn () => ...)`.
- Domain events fire **after** commit using `DB::afterCommit()` or queued listeners. Never inside the transaction.

```php
return DB::transaction(function () use ($dto) {
    $service = $this->repository->create($dto);
    DB::afterCommit(fn () => event(new ServiceCreated($service)));
    return $service;
});
```

## Per-type Actions (the rental/sale/digital pattern)

For type-aware operations, create three parallel Actions with type-specific names:

- `CreateRentalServiceAction`, `CreateSaleServiceAction`, `CreateDigitalServiceAction`
- `UpdateRentalServiceAction`, `UpdateSaleServiceAction`, `UpdateDigitalServiceAction`
- `ImportRentalServicesFromExcelAction`, `ImportSaleServicesFromExcelAction`, `ImportDigitalServicesFromExcelAction`

Inside any of these:

- Operate on the type's specific Form Request DTO.
- Persist into `services` + the matching detail table within one transaction.
- Fire type-specific or generic domain events as appropriate.

For cross-type Actions (one Action that handles all three), use `match($enum)`:

```php
public function execute(Service $service): Refund
{
    return match ($service->product_type) {
        ProductType::Rental  => $this->rentalRefundCalculator->compute($service),
        ProductType::Sale    => $this->saleRefundCalculator->compute($service),
        ProductType::Digital => $this->digitalRefundCalculator->compute($service),
    };
}
```

**Never** use if/elseif on `$type === 'rental'`.

## Idempotency

For Actions called from payment-mutating endpoints:

- Check the `idempotency_keys` table at the top.
- Store the result keyed by (route, key, user_id) with 24h TTL.
- Return the cached result on duplicate calls within the window.

## What forbids an Action

- A second public method — **fail**.
- Instantiating models with `new` instead of going through the repository — **fail unless** there is no repository for this aggregate yet.
- Business logic inside the Model that is duplicated here — **fail** (move it all into the Action).
- An if/elseif chain over `product_type` strings — **fail** (use `match($enum)`).

## Naming

- `{Verb}{Noun}Action` for cross-type: `PublishServiceAction`, `CancelBookingAction`.
- `{Verb}{Type}{Noun}Action` for per-type: `CreateRentalServiceAction`.
