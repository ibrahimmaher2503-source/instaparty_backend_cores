<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Filament\Widgets\ExcelImportsWithErrorsWidget;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    // Clear development-seeded import data so widget counts reflect only test data
    DB::table('excel_import_errors')->delete();
    DB::table('excel_imports')->delete();
});

it('counts import with failed status within 7 days', function (): void {
    ExcelImport::factory()->create([
        'status' => 'failed',
        'error_rows' => 0,
        'created_at' => now(),
    ]);

    $widget = new ExcelImportsWithErrorsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(1);
})->group('widgets', 'admin', 'catalog');

it('counts import with error_rows greater than zero within 7 days', function (): void {
    ExcelImport::factory()->create([
        'status' => 'processing',
        'error_rows' => 3,
        'created_at' => now(),
    ]);

    $widget = new ExcelImportsWithErrorsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(1);
})->group('widgets', 'admin', 'catalog');

it('returns zero for failed import older than 7 days', function (): void {
    ExcelImport::factory()->create([
        'status' => 'failed',
        'error_rows' => 0,
        'created_at' => now()->subDays(8),
    ]);

    $widget = new ExcelImportsWithErrorsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'catalog');

it('returns zero for completed import with no errors', function (): void {
    ExcelImport::factory()->create([
        'status' => 'completed',
        'error_rows' => 0,
        'created_at' => now(),
    ]);

    $widget = new ExcelImportsWithErrorsWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'catalog');
