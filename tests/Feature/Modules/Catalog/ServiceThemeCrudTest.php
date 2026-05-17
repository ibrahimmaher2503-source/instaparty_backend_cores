<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\CreateServiceThemeAction;
use App\Modules\Catalog\Application\Actions\DeleteServiceThemeAction;
use App\Modules\Catalog\Application\Actions\UpdateServiceThemeAction;
use App\Modules\Catalog\Application\DTOs\ServiceThemeDTO;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->admin = User::factory()->phoneVerified()->superAdmin()->create();
});

it('creates a service theme via the Action', function (): void {
    $dto = new ServiceThemeDTO(
        code: 'test-princess',
        name: ['en' => 'Princess', 'ar' => 'أميرة'],
        isActive: true,
    );

    $theme = app(CreateServiceThemeAction::class)->execute($dto, $this->admin);

    expect($theme->code)->toBe('test-princess');
    expect($theme->getTranslation('name', 'ar'))->toBe('أميرة');
    expect($theme->public_id)->not->toBeNull();
    expect(DB::table('audit_logs')->where('action', 'service_theme_created')->count())->toBe(1);
})->group('catalog', 'taxonomy', 'themes');

it('updates a service theme', function (): void {
    $theme = app(CreateServiceThemeAction::class)->execute(new ServiceThemeDTO(
        code: 'test-hero',
        name: ['en' => 'Hero', 'ar' => 'بطل'],
    ), $this->admin);

    $updated = app(UpdateServiceThemeAction::class)->execute(
        $theme,
        new ServiceThemeDTO(
            code: 'test-hero',
            name: ['en' => 'Superhero', 'ar' => 'بطل خارق'],
            isActive: false,
        ),
        $this->admin,
    );

    expect($updated->getTranslation('name', 'en'))->toBe('Superhero');
    expect($updated->is_active)->toBeFalse();
})->group('catalog', 'taxonomy', 'themes');

it('deletes a service theme', function (): void {
    $theme = app(CreateServiceThemeAction::class)->execute(new ServiceThemeDTO(
        code: 'test-trash',
        name: ['en' => 'T', 'ar' => 'ت'],
    ), $this->admin);

    app(DeleteServiceThemeAction::class)->execute($theme, $this->admin);

    expect(ServiceTheme::query()->find($theme->id))->toBeNull();
})->group('catalog', 'taxonomy', 'themes');

it('attaches themes to a service via the new pivot', function (): void {
    $vendorUser = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
    $service = Service::factory()->rental()->create(['vendor_profile_id' => $vendor->id]);

    $theme1 = app(CreateServiceThemeAction::class)->execute(new ServiceThemeDTO(
        code: 'test-th1',
        name: ['en' => 'T1', 'ar' => 'ت1'],
    ), $this->admin);
    $theme2 = app(CreateServiceThemeAction::class)->execute(new ServiceThemeDTO(
        code: 'test-th2',
        name: ['en' => 'T2', 'ar' => 'ت2'],
    ), $this->admin);

    $service->themes()->sync([
        $theme1->id => ['sort_order' => 0],
        $theme2->id => ['sort_order' => 1],
    ]);

    expect($service->themes()->count())->toBe(2);
    expect($service->themes->pluck('code')->all())->toBe(['test-th1', 'test-th2']);
    expect($theme1->services()->pluck('id')->all())->toContain($service->id);
})->group('catalog', 'taxonomy', 'themes', 'pivot');

it('rejects duplicate theme code', function (): void {
    app(CreateServiceThemeAction::class)->execute(new ServiceThemeDTO(
        code: 'test-dup',
        name: ['en' => 'A', 'ar' => 'أ'],
    ), $this->admin);

    expect(fn () => app(CreateServiceThemeAction::class)->execute(new ServiceThemeDTO(
        code: 'test-dup',
        name: ['en' => 'B', 'ar' => 'ب'],
    ), $this->admin))->toThrow(\Illuminate\Database\QueryException::class);
})->group('catalog', 'taxonomy', 'themes');
