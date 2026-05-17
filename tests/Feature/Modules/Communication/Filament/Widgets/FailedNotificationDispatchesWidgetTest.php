<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Filament\Widgets\FailedNotificationDispatchesWidget;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    // Clear development-seeded dispatch data so widget counts reflect only test data
    DB::table('notification_dispatches')->delete();
});

it('counts failed dispatch created within 24 hours', function (): void {
    NotificationDispatch::factory()->create([
        'status' => DispatchStatus::Failed->value,
        'created_at' => now()->subMinutes(10),
    ]);

    $widget = new FailedNotificationDispatchesWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(1);
})->group('widgets', 'admin', 'communication');

it('counts bounced dispatch created within 24 hours', function (): void {
    NotificationDispatch::factory()->create([
        'status' => DispatchStatus::Bounced->value,
        'created_at' => now()->subMinutes(10),
    ]);

    $widget = new FailedNotificationDispatchesWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(1);
})->group('widgets', 'admin', 'communication');

it('returns zero for failed dispatch older than 24 hours', function (): void {
    NotificationDispatch::factory()->create([
        'status' => DispatchStatus::Failed->value,
        'created_at' => now()->subHours(25),
    ]);

    $widget = new FailedNotificationDispatchesWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'communication');

it('widget url contains status filter string', function (): void {
    $widget = new FailedNotificationDispatchesWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getUrl())->toContain('status');
})->group('widgets', 'admin', 'communication');
