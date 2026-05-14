<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Communication\Database\Seeders\DocExpiryNotificationTemplateSeeder;
use App\Modules\Identity\Application\Actions\AutoSuspendForExpiredDocAction;
use App\Modules\Identity\Application\Actions\SetDocumentExpiryAction;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Enums\ComplianceEventType;
use App\Modules\Identity\Domain\Events\VendorAutoSuspended;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorComplianceEvent;
use Database\Factories\VendorDocumentFactory;
use Database\Factories\VendorProfileFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create required roles for Filament Shield (if not already exists)
    Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

    app(DocExpiryNotificationTemplateSeeder::class)->run();
});

it('sets document expiry with future date correctly', function () {
    $vendor = VendorProfileFactory::new()->create();
    $document = VendorDocumentFactory::new()->for($vendor)->create([
        'expires_at' => null,
        'is_critical' => false,
    ]);

    $expiryDate = Carbon::now()->addDays(30);
    $result = app(SetDocumentExpiryAction::class)->execute($document, $expiryDate, true);

    expect($result->expires_at->toDateString())->toEqual($expiryDate->toDateString());
    expect($result->is_critical)->toBeTrue();
    expect($result->fresh()->expires_at->toDateString())->toEqual($expiryDate->toDateString());
    expect($result->fresh()->is_critical)->toBeTrue();
})->group('compliance');

it('throws InvalidArgumentException when expiry date is in the past', function () {
    $vendor = VendorProfileFactory::new()->create();
    $document = VendorDocumentFactory::new()->for($vendor)->create();

    $pastDate = Carbon::now()->subDays(1);

    expect(fn () => app(SetDocumentExpiryAction::class)->execute($document, $pastDate, true))
        ->toThrow(InvalidArgumentException::class);
})->group('compliance');

it('creates compliance event for 30-day reminder', function () {
    $vendor = VendorProfileFactory::new()->create();
    $expiryDate = today()->addDays(30);
    $document = VendorDocumentFactory::new()->for($vendor)->create([
        'expires_at' => $expiryDate->toDateString(),
        'is_critical' => true,
        'last_reminder_sent_at' => null,
    ]);

    $this->artisan('identity:check-document-expiry')->assertSuccessful();

    expect(VendorComplianceEvent::where('document_id', $document->id)
        ->where('event_type', ComplianceEventType::ReminderSent->value)
        ->exists())->toBeTrue();
})->group('compliance');

it('creates compliance event for 14-day reminder', function () {
    $vendor = VendorProfileFactory::new()->create();
    $expiryDate = today()->addDays(14);
    $document = VendorDocumentFactory::new()->for($vendor)->create([
        'expires_at' => $expiryDate->toDateString(),
        'is_critical' => true,
        'last_reminder_sent_at' => null,
    ]);

    $this->artisan('identity:check-document-expiry')->assertSuccessful();

    expect(VendorComplianceEvent::where('document_id', $document->id)
        ->where('event_type', ComplianceEventType::ReminderSent->value)
        ->count())->toBeGreaterThan(0);
})->group('compliance');

it('creates compliance event for 7-day reminder', function () {
    $vendor = VendorProfileFactory::new()->create();
    $expiryDate = today()->addDays(7);
    $document = VendorDocumentFactory::new()->for($vendor)->create([
        'expires_at' => $expiryDate->toDateString(),
        'is_critical' => true,
        'last_reminder_sent_at' => null,
    ]);

    $this->artisan('identity:check-document-expiry')->assertSuccessful();

    expect(VendorComplianceEvent::where('document_id', $document->id)
        ->where('event_type', ComplianceEventType::ReminderSent->value)
        ->exists())->toBeTrue();
})->group('compliance');

it('creates compliance event for 1-day reminder', function () {
    $vendor = VendorProfileFactory::new()->create();
    $expiryDate = today()->addDays(1);
    $document = VendorDocumentFactory::new()->for($vendor)->create([
        'expires_at' => $expiryDate->toDateString(),
        'is_critical' => true,
        'last_reminder_sent_at' => null,
    ]);

    $this->artisan('identity:check-document-expiry')->assertSuccessful();

    expect(VendorComplianceEvent::where('document_id', $document->id)
        ->where('event_type', ComplianceEventType::ReminderSent->value)
        ->exists())->toBeTrue();
})->group('compliance');

it('does not send duplicate reminder when run twice same day', function () {
    $vendor = VendorProfileFactory::new()->create();
    $expiryDate = today()->addDays(30);
    $document = VendorDocumentFactory::new()->for($vendor)->create([
        'expires_at' => $expiryDate->toDateString(),
        'is_critical' => true,
        'last_reminder_sent_at' => null,
    ]);

    $this->artisan('identity:check-document-expiry')->assertSuccessful();

    $eventCountAfterFirst = VendorComplianceEvent::where('document_id', $document->id)
        ->where('event_type', ComplianceEventType::ReminderSent->value)
        ->count();

    // Reset the reminders for second run to test idempotency would fail without the guard
    // But since the guard is last_reminder_sent_at->isToday(), it will prevent a second send
    $this->artisan('identity:check-document-expiry')->assertSuccessful();

    $eventCountAfterSecond = VendorComplianceEvent::where('document_id', $document->id)
        ->where('event_type', ComplianceEventType::ReminderSent->value)
        ->count();

    expect($eventCountAfterSecond)->toEqual($eventCountAfterFirst);
})->group('compliance');

it('auto-suspends vendor when critical doc expires', function () {
    $vendor = VendorProfileFactory::new()->create();
    $document = VendorDocumentFactory::new()->for($vendor)->create([
        'expires_at' => today()->subDay()->toDateString(),
        'is_critical' => true,
    ]);

    $this->artisan('identity:check-document-expiry')->assertSuccessful();

    expect($vendor->fresh()->approval_status)->toBe(ApprovalStatus::Suspended);
    expect(VendorComplianceEvent::where('document_id', $document->id)
        ->where('event_type', ComplianceEventType::AutoSuspended->value)
        ->exists())->toBeTrue();
})->group('compliance');

it('dispatches VendorAutoSuspended event', function () {
    Event::fake();

    $vendor = VendorProfileFactory::new()->create();
    $document = VendorDocumentFactory::new()->for($vendor)->create([
        'expires_at' => today()->subDay()->toDateString(),
        'is_critical' => true,
    ]);

    app(AutoSuspendForExpiredDocAction::class)->execute($document);

    Event::assertDispatched(VendorAutoSuspended::class, function (VendorAutoSuspended $event) use ($document, $vendor) {
        return $event->vendorProfile->id === $vendor->id && $event->document->id === $document->id;
    });
})->group('compliance');

it('revokes per-type approvals after auto-suspend', function () {
    $vendor = VendorProfileFactory::new()->create();
    VendorApprovedProductType::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'product_type' => ProductType::Rental,
    ]);
    VendorApprovedProductType::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'product_type' => ProductType::Sale,
    ]);

    $document = VendorDocumentFactory::new()->for($vendor)->create([
        'expires_at' => today()->subDay()->toDateString(),
        'is_critical' => true,
    ]);

    $this->artisan('identity:check-document-expiry')->assertSuccessful();

    expect(VendorApprovedProductType::where('vendor_profile_id', $vendor->id)
        ->whereNotNull('revoked_at')
        ->count())->toEqual(2);
})->group('compliance');

it('does not suspend non-critical expired doc', function () {
    $vendor = VendorProfileFactory::new()->create();
    $document = VendorDocumentFactory::new()->for($vendor)->create([
        'expires_at' => today()->subDay()->toDateString(),
        'is_critical' => false,
    ]);

    $this->artisan('identity:check-document-expiry')->assertSuccessful();

    expect($vendor->fresh()->approval_status)->not()->toBe(ApprovalStatus::Suspended);
    expect(VendorComplianceEvent::where('document_id', $document->id)
        ->where('event_type', ComplianceEventType::AutoSuspended->value)
        ->exists())->toBeFalse();
})->group('compliance');
