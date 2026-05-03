<?php

declare(strict_types=1);

it('loyalty module actions do not use if/elseif chains on product type strings', function (): void {
    expect('App\\Modules\\Loyalty\\Application\\Actions')
        ->not->toUse('App\\Modules\\Catalog\\Domain\\Enums\\ProductType');
})->group('architecture', 'loyalty');
