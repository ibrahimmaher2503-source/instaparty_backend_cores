<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\PublishServiceAction;
use App\Modules\Catalog\Application\Actions\RejectServiceAction;
use App\Modules\Catalog\Application\Actions\RequestRentalServiceChangesAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\ServicePublished;
use App\Modules\Catalog\Domain\Events\ServiceRejected;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class)->group('catalog', 'service-moderation');

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->admin = User::factory()->asAdmin()->create();
    $this->vendorProfile = VendorProfile::factory()->approved()->create();
});

it('approves and publishes pending services for every product type', function (ProductType $productType): void {
    Event::fake([ServicePublished::class]);

    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->create([
            'product_type' => $productType,
            'status' => ServiceStatus::PendingReview,
        ]);

    app(PublishServiceAction::class)->execute($service, $this->admin);

    $service->refresh();

    expect($service->status)->toBe(ServiceStatus::Published)
        ->and($service->moderated_by)->toBe($this->admin->id)
        ->and($service->moderated_at)->not->toBeNull();

    Event::assertDispatched(
        ServicePublished::class,
        fn (ServicePublished $event): bool => $event->service->is($service),
    );
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('rejects pending services with bilingual moderation notes', function (): void {
    Event::fake([ServiceRejected::class]);

    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->sale()
        ->pendingReview()
        ->create();

    app(RejectServiceAction::class)->execute($service, [
        'en' => 'Photos are not clear enough.',
        'ar' => 'Arabic rejection reason is required.',
    ], $this->admin);

    $service->refresh();

    expect($service->status)->toBe(ServiceStatus::Rejected)
        ->and($service->getTranslation('moderation_notes', 'en'))->toBe('Photos are not clear enough.')
        ->and($service->getTranslation('moderation_notes', 'ar'))->toBe('Arabic rejection reason is required.')
        ->and($service->moderated_by)->toBe($this->admin->id)
        ->and($service->moderated_at)->not->toBeNull();

    Event::assertDispatched(
        ServiceRejected::class,
        fn (ServiceRejected $event): bool => $event->service->is($service),
    );
});

it('blocks rejection when either moderation note locale is missing', function (): void {
    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->digital()
        ->pendingReview()
        ->create();

    expect(fn () => app(RejectServiceAction::class)->execute($service, [
        'en' => 'Missing Arabic reason.',
    ], $this->admin))->toThrow(ValidationException::class);

    $service->refresh();

    expect($service->status)->toBe(ServiceStatus::PendingReview)
        ->and($service->moderation_notes)->toBeNull();
});

it('blocks approve and reject decisions for non-pending services', function (): void {
    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->published()
        ->create();

    expect(fn () => app(PublishServiceAction::class)->execute($service, $this->admin))
        ->toThrow(ValidationException::class);

    expect(fn () => app(RejectServiceAction::class)->execute($service, [
        'en' => 'No longer pending.',
        'ar' => 'No longer pending.',
    ], $this->admin))->toThrow(ValidationException::class);
});

it('exposes row moderation decisions only for eligible pending services', function (): void {
    $policy = app(ServicePolicy::class);

    $pending = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->rental()
        ->pendingReview()
        ->create();

    $published = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->rental()
        ->published()
        ->create();

    expect($policy->approve($this->admin, $pending))->toBeTrue()
        ->and($policy->reject($this->admin, $pending))->toBeTrue()
        ->and($policy->requestChanges($this->admin, $pending))->toBeTrue()
        ->and($policy->approve($this->admin, $published))->toBeFalse()
        ->and($policy->reject($this->admin, $published))->toBeFalse()
        ->and($policy->requestChanges($this->admin, $published))->toBeFalse();
});

it('delegates request edits to the accepted service change-request workflow', function (): void {
    $service = Service::factory()
        ->for($this->vendorProfile, 'vendor')
        ->rental()
        ->pendingReview()
        ->create();

    app(RequestRentalServiceChangesAction::class)->execute($service, [
        [
            'field_path' => 'name',
            'requested_change_en' => 'Use a clearer title.',
            'requested_change_ar' => 'Use a clearer title.',
        ],
    ], $this->admin, 'service-moderation-request-edits');

    $service->refresh();
    $changeRequest = ChangeRequest::query()
        ->where('subject_type', 'service')
        ->where('subject_id', $service->id)
        ->first();

    expect($service->status)->toBe(ServiceStatus::ChangesRequested)
        ->and($changeRequest)->not->toBeNull()
        ->and($changeRequest->items()->count())->toBe(1);
});
