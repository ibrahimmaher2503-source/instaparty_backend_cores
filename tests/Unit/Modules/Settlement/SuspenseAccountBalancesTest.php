<?php

declare(strict_types=1);

use App\Modules\Settlement\Domain\Enums\SuspenseAccount;

/**
 * T063 — Every SuspenseAccount case has a label and a unique int value.
 * Double-entry ledger entries always pair exactly two suspense accounts;
 * this test verifies the enum is complete and non-overlapping.
 */

it('all SuspenseAccount cases have unique integer values', function (): void {
    $values = collect(SuspenseAccount::cases())->pluck('value')->all();
    expect(count($values))->toBe(count(array_unique($values)));
})->group('ledger', 'us1');

it('all SuspenseAccount cases have non-empty labels', function (SuspenseAccount $account): void {
    expect($account->label())->toBeString()->not()->toBeEmpty();
})->with(SuspenseAccount::cases())->group('ledger', 'us1');

it('SuspenseAccount integer range is 1-7 (no gaps, no extras)', function (): void {
    $values = collect(SuspenseAccount::cases())->pluck('value')->sort()->values()->all();
    expect($values)->toBe([1, 2, 3, 4, 5, 6, 7]);
})->group('ledger', 'us1');
