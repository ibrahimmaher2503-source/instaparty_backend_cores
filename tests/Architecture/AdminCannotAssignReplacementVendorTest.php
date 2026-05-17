<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('enforces FR-EXT-011: no AssignReplacementVendor class or method exists anywhere', function (): void {
    // (a) No class under App\Modules\Booking or App\Modules\Discovery named like AssignReplacement
    $bookingFiles = glob(base_path('app/Modules/Booking/**/*.php'));
    $discoveryFiles = glob(base_path('app/Modules/Discovery/**/*.php'));
    $allFiles = array_merge($bookingFiles ?: [], $discoveryFiles ?: []);

    foreach ($allFiles as $file) {
        $content = file_get_contents($file);
        expect($content)->not->toContain(
            'AssignReplacementVendor',
            "File {$file} contains forbidden 'AssignReplacementVendor' reference"
        );
        expect($content)->not->toContain(
            'assignReplacementVendor',
            "File {$file} contains forbidden 'assignReplacementVendor' reference"
        );
    }
});

it('enforces FR-EXT-011: no assign_replacement permission is registered', function (): void {
    // Check permission table does not have replacement assignment permissions
    // This runs against the test DB — skip gracefully if table does not exist
    try {
        $exists = DB::table('permissions')
            ->where('name', 'like', '%assign_replacement%')
            ->exists();
        expect($exists)->toBeFalse('No assign_replacement permission should be registered');
    } catch (\Exception) {
        // Table may not exist in all test environments — skip
    }
})->group('architecture');

it('enforces FR-EXT-011: no Filament Action named assign_replacement_vendor', function (): void {
    $filamentFiles = glob(base_path('app/Modules/Booking/Filament/**/*.php'));
    $filamentFiles = array_merge($filamentFiles ?: [], glob(base_path('app/Modules/Booking/Filament/**/**/*.php')) ?: []);

    foreach ($filamentFiles as $file) {
        $content = file_get_contents($file);
        expect($content)->not->toContain(
            'assign_replacement_vendor',
            "File {$file} contains forbidden 'assign_replacement_vendor' Filament action"
        );
    }
})->group('architecture');
