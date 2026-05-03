<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

// T080 — Constitution rule IX: domain events must fire after DB::transaction commit.
// Any Communication Action that calls event() or ::dispatch() must also reference
// DB::afterCommit to guarantee post-commit delivery.

it('communication actions that dispatch events also reference DB::afterCommit', function (): void {
    $offenders = [];

    $finder = (new Finder)
        ->files()
        ->in(base_path('app/Modules/Communication/Application/Actions'))
        ->name('*.php');

    foreach ($finder as $file) {
        $code = $file->getContents();

        $hasEventDispatch = preg_match('/(?<![>:])\bevent\s*\(/', $code)
            || preg_match('/::dispatch\s*\(/', $code);

        if (! $hasEventDispatch) {
            continue;
        }

        if (! str_contains($code, 'DB::afterCommit')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe(
        [],
        'These Action files dispatch domain events without DB::afterCommit: '.implode(', ', $offenders)
    );
})->group('communication');
