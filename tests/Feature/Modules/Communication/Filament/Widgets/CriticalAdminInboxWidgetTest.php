<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Filament\Widgets\CriticalAdminInboxWidget;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
});

it('counts critical unread inbox item', function (): void {
    AdminInboxItem::factory()->create([
        'severity' => AdminInboxSeverity::Critical->value,
        'status' => AdminInboxStatus::Unread->value,
    ]);

    $widget = new CriticalAdminInboxWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(1);
})->group('widgets', 'admin', 'communication');

it('returns zero when critical item is resolved', function (): void {
    AdminInboxItem::factory()->create([
        'severity' => AdminInboxSeverity::Critical->value,
        'status' => AdminInboxStatus::Resolved->value,
    ]);

    $widget = new CriticalAdminInboxWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'communication');

it('returns zero when warning severity item is unread', function (): void {
    AdminInboxItem::factory()->create([
        'severity' => AdminInboxSeverity::Warning->value,
        'status' => AdminInboxStatus::Unread->value,
    ]);

    $widget = new CriticalAdminInboxWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getValue())->toBe(0);
})->group('widgets', 'admin', 'communication');

it('widget url contains severity=critical filter string', function (): void {
    $widget = new CriticalAdminInboxWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($stats[0]->getUrl())->toContain('severity');
})->group('widgets', 'admin', 'communication');
