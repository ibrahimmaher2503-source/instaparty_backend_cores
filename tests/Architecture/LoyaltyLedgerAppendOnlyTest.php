<?php

declare(strict_types=1);

use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;

it('loyalty ledger model registers updating and deleting guards in booted', function (): void {
    // Instantiate to trigger booted()
    new LoyaltyLedgerEntry;

    $dispatcher = LoyaltyLedgerEntry::getEventDispatcher();
    $class = LoyaltyLedgerEntry::class;

    $updatingListeners = $dispatcher->getListeners("eloquent.updating: {$class}");
    $deletingListeners = $dispatcher->getListeners("eloquent.deleting: {$class}");

    expect(count($updatingListeners))->toBeGreaterThan(0)
        ->and(count($deletingListeners))->toBeGreaterThan(0);
})->group('architecture', 'loyalty');

it('loyalty ledger model has no updated_at', function (): void {
    $entry = new LoyaltyLedgerEntry;
    expect($entry::UPDATED_AT)->toBeNull();
})->group('architecture', 'loyalty');
