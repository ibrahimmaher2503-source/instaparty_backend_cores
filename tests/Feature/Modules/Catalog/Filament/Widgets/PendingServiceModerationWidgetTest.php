<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Widgets\PendingServiceModerationWidget;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    Permission::findOrCreate('view_any_rental_service', 'web');
    // Reset seeded services so development data doesn't pollute widget counts
    DB::table('services')->update(['status' => 'published']);
});

it('returns per-type counts for pending_review services', function (): void {
    Service::factory()->create(['status' => ServiceStatus::PendingReview->value, 'product_type' => ProductType::Rental->value]);
    Service::factory()->create(['status' => ServiceStatus::PendingReview->value, 'product_type' => ProductType::Digital->value]);
    Service::factory()->create(['status' => ServiceStatus::Published->value, 'product_type' => ProductType::Sale->value]);

    $widget = new PendingServiceModerationWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    $statsByLabel = collect($stats)->keyBy(fn ($s) => $s->getLabel());
    expect($statsByLabel[__('catalog::widgets.pending_service_moderation_rental_heading')]->getValue())->toBe(1);
    expect($statsByLabel[__('catalog::widgets.pending_service_moderation_sale_heading')]->getValue())->toBe(0);
    expect($statsByLabel[__('catalog::widgets.pending_service_moderation_digital_heading')]->getValue())->toBe(1);
})->group('widgets', 'admin', 'catalog', 'rental', 'sale', 'digital');

it('returns zero for all types when no pending_review services exist', function (): void {
    Service::factory()->create(['status' => ServiceStatus::Published->value, 'product_type' => ProductType::Rental->value]);

    $widget = new PendingServiceModerationWidget;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    foreach ($stats as $stat) {
        expect($stat->getValue())->toBe(0);
    }
})->group('widgets', 'admin', 'catalog', 'rental', 'sale', 'digital');

it('canView returns true with view_any_rental_service permission', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo('view_any_rental_service');
    $this->actingAs($admin);

    expect(PendingServiceModerationWidget::canView())->toBeTrue();
})->group('widgets', 'admin', 'catalog');
