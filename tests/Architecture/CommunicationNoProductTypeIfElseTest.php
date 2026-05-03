<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

// T079 — Constitution rule: no if/elseif chains on product type strings.
// Communication module uses event_key strings (rental.*, sale.*, digital.*) as plain
// data, not type switches, so no match($enum) needed. But guard against accidental
// if/elseif on raw 'rental'/'sale'/'digital' string comparisons.

it('communication module has no if/elseif chains on product type strings', function (): void {
    $offenders = [];

    $finder = (new Finder)
        ->files()
        ->in(base_path('app/Modules/Communication'))
        ->name('*.php');

    foreach ($finder as $file) {
        $code = $file->getContents();

        // Detect: if ($x === 'rental') ... elseif ($x === 'sale') or similar
        if (preg_match('/if\s*\(\s*\$\w+\s*===?\s*[\'"](?:rental|sale|digital)[\'"]/', $code)
            && preg_match('/elseif\s*\(\s*\$\w+\s*===?\s*[\'"](?:rental|sale|digital)[\'"]/', $code)) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe(
        [],
        'These files use if/elseif chains on product type strings (use match($enum) instead): '.implode(', ', $offenders)
    );
})->group('communication');
