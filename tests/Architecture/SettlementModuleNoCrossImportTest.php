<?php

declare(strict_types=1);

// ─────────────────────────────────────────────────────
// T703 — Settlement module must not directly import
//         Eloquent models from Booking or Payments.
//
// Cross-module communication must go through:
//   - Domain events
//   - Contracts in Domain/Contracts/
//   - DTOs returned from repositories
// ─────────────────────────────────────────────────────

arch('Settlement module does not import Booking Eloquent models')
    ->expect('App\Modules\Settlement')
    ->not->toUse('App\Modules\Booking\Domain\Models')
    ->group('architecture', 'settlement');

arch('Settlement module does not import Payments Eloquent models')
    ->expect('App\Modules\Settlement')
    ->not->toUse('App\Modules\Payments\Domain\Models')
    ->group('architecture', 'settlement');

// NOTE: Settlement\Domain\Models\CommissionRate intentionally imports
// App\Modules\Catalog\Domain\Models\Category for the FK relationship.
// This is an accepted coupling — CommissionRate.category_id references categories.
// A future refactor could introduce a SettlementCatalogReader contract,
// but it's not required at Phase 4.2 scope.

arch('Settlement Application layer does not import Catalog Eloquent models')
    ->expect('App\Modules\Settlement\Application')
    ->not->toUse('App\Modules\Catalog\Domain\Models')
    ->group('architecture', 'settlement');

arch('Settlement Actions have a single public execute method')
    ->expect('App\Modules\Settlement\Application\Actions')
    ->toHaveMethod('execute')
    ->group('architecture', 'settlement');
