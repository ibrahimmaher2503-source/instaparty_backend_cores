<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Heuristic: an Action that dispatches domain events MUST also use DB::afterCommit
 * to wrap them. A file that calls `event(...)` or `::dispatch(...)` but never references
 * `DB::afterCommit` is almost certainly firing events inside (or outside) a transaction
 * without the post-commit guarantee — Constitution IX violation.
 */
it('payments actions that dispatch events also reference DB::afterCommit', function (): void {
    $offenders = [];

    $finder = (new Finder)
        ->files()
        ->in(base_path('app/Modules/Payments/Application/Actions'))
        ->name('*.php');

    foreach ($finder as $file) {
        $code = $file->getContents();

        $hasEventDispatch = preg_match('/(?<![>:])\bevent\s*\(/', $code) || preg_match('/::dispatch\s*\(/', $code);

        if (! $hasEventDispatch) {
            continue;
        }

        if (! str_contains($code, 'DB::afterCommit')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], 'These Action files dispatch domain events without using DB::afterCommit anywhere: '.implode(', ', $offenders));
});
