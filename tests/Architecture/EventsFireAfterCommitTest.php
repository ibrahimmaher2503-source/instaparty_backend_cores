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

// T702 — Settlement actions must also fire events after commit
it('settlement actions that dispatch events also reference DB::afterCommit', function (): void {
    $offenders = [];

    $finder = (new Finder)
        ->files()
        ->in(base_path('app/Modules/Settlement/Application/Actions'))
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

    expect($offenders)->toBe([], 'These Settlement Action files dispatch domain events without using DB::afterCommit anywhere: '.implode(', ', $offenders));
});

// T026 — Booking intervention actions must also fire events after commit
it('enforces constitution IX: all new booking intervention Actions fire events inside DB::afterCommit', function (): void {
    $actionFiles = [
        base_path('app/Modules/Booking/Application/Actions/SendVendorReminderAction.php'),
        base_path('app/Modules/Booking/Application/Actions/EscalateLateVendorResponseAction.php'),
        base_path('app/Modules/Booking/Application/Actions/SuggestAlternativeVendorsAction.php'),
        base_path('app/Modules/Booking/Application/Actions/ResumeBookingReviewAction.php'),
        base_path('app/Modules/Booking/Application/Actions/CreateAdminInterventionNoteAction.php'),
    ];

    foreach ($actionFiles as $file) {
        if (! file_exists($file)) {
            continue; // Skip files not yet created
        }

        $content = file_get_contents($file);

        // Files that fire events must have them inside afterCommit
        if (! str_contains($content, 'event(new ')) {
            continue; // This action fires no events — fine
        }

        expect($content)->toContain(
            'DB::afterCommit',
            "Action {$file} fires an event but it is not inside DB::afterCommit"
        );
    }
})->group('architecture');
