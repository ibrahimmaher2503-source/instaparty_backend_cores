<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Actions\BulkApproveServicesAction;
use App\Modules\Catalog\Filament\Actions\BulkArchiveServicesAction;
use App\Modules\Catalog\Filament\Actions\BulkRejectServicesAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class)->group('catalog', 'service-moderation');

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->admin = User::factory()->asAdmin()->create();
    $this->vendorProfile = VendorProfile::factory()->approved()->create();
});

it('bulk approves 50 pending services in one operation', function (): void {
    $services = Service::factory()
        ->count(50)
        ->for($this->vendorProfile, 'vendor')
        ->create([
            'product_type' => ProductType::Rental,
            'status' => ServiceStatus::PendingReview,
        ]);

    $processed = BulkApproveServicesAction::execute($services, $this->admin);

    expect($processed)->toBe(50);

    $services->each(function (Service $service): void {
        $service->refresh();

        expect($service->status)->toBe(ServiceStatus::Published)
            ->and($service->moderated_by)->toBe($this->admin->id)
            ->and($service->moderated_at)->not->toBeNull();
    });
});

it('bulk rejects pending services with shared bilingual notes', function (): void {
    $services = Service::factory()
        ->count(3)
        ->for($this->vendorProfile, 'vendor')
        ->create([
            'product_type' => ProductType::Sale,
            'status' => ServiceStatus::PendingReview,
        ]);

    $processed = BulkRejectServicesAction::execute($services, [
        'en' => 'Shared rejection reason.',
        'ar' => 'Shared Arabic reason.',
    ], $this->admin);

    expect($processed)->toBe(3);

    $services->each(function (Service $service): void {
        $service->refresh();

        expect($service->status)->toBe(ServiceStatus::Rejected)
            ->and($service->getTranslation('moderation_notes', 'en'))->toBe('Shared rejection reason.')
            ->and($service->getTranslation('moderation_notes', 'ar'))->toBe('Shared Arabic reason.');
    });
});

it('bulk reject requires both shared note locales before changing any service', function (): void {
    $services = Service::factory()
        ->count(2)
        ->for($this->vendorProfile, 'vendor')
        ->create([
            'product_type' => ProductType::Digital,
            'status' => ServiceStatus::PendingReview,
        ]);

    expect(fn () => BulkRejectServicesAction::execute($services, [
        'en' => 'Only English.',
    ], $this->admin))->toThrow(ValidationException::class);

    $services->each(function (Service $service): void {
        expect($service->refresh()->status)->toBe(ServiceStatus::PendingReview);
    });
});

it('bulk archives eligible selected services', function (): void {
    $services = Service::factory()
        ->count(4)
        ->for($this->vendorProfile, 'vendor')
        ->published()
        ->create([
            'product_type' => ProductType::Rental,
        ]);

    $processed = BulkArchiveServicesAction::execute($services, $this->admin);

    expect($processed)->toBe(4);

    $services->each(function (Service $service): void {
        expect($service->refresh()->status)->toBe(ServiceStatus::Archived);
    });
});

it('bulk approve rolls back if any selected service is no longer eligible', function (): void {
    $pending = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->pendingReview()
        ->create();

    $published = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->published()
        ->create([
            'product_type' => $pending->product_type,
        ]);

    expect(fn () => BulkApproveServicesAction::execute(collect([$pending, $published]), $this->admin))
        ->toThrow(ValidationException::class);

    expect($pending->refresh()->status)->toBe(ServiceStatus::PendingReview)
        ->and($published->refresh()->status)->toBe(ServiceStatus::Published);
});
