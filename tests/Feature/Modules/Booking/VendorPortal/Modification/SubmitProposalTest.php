<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AddBookingModificationItemAction;
use App\Modules\Booking\Application\Actions\CreateBookingModificationAction;
use App\Modules\Booking\Application\Actions\SubmitBookingModificationProposalAction;
use App\Modules\Booking\Application\DTOs\AddBookingModificationItemDTO;
use App\Modules\Booking\Application\DTOs\CreateBookingModificationDTO;
use App\Modules\Booking\Application\DTOs\SubmitBookingModificationProposalDTO;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\VendorModificationProposed;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Shared\Domain\Models\StateTransition;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../../BookingTestHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);

    $this->data = makeSubmittedBookingWithVendor();
    $this->draft = app(CreateBookingModificationAction::class)->execute(new CreateBookingModificationDTO(
        bookingVendorId: $this->data['bookingVendor']->id,
        vendorProfileId: $this->data['vendor']->id,
        proposedByUserId: $this->data['vendor']->user->id,
    ));
    app(AddBookingModificationItemAction::class)->execute(new AddBookingModificationItemDTO(
        bookingModificationId: $this->draft->id,
        vendorProfileId: $this->data['vendor']->id,
        proposedByUserId: $this->data['vendor']->user->id,
        changeKind: ModificationChangeKind::Update,
        changeType: ModificationProposalKind::ChangePrice,
        targetBookingItemId: $this->data['item']->id,
        payload: ['unit_price_minor' => 60000, 'unit_price_currency' => 'EGP'],
    ));
});

it('flips draft to pending and transitions the booking to customer_review', function (): void {
    Event::fake();

    $modification = app(SubmitBookingModificationProposalAction::class)->execute(
        new SubmitBookingModificationProposalDTO(
            bookingModificationId: $this->draft->id,
            vendorProfileId: $this->data['vendor']->id,
            proposedByUserId: $this->data['vendor']->user->id,
            vendorExplanation: ['en' => 'Setup fee', 'ar' => 'رسوم تركيب'],
        ),
    );

    expect($modification->status)->toBe(ModificationStatus::Pending);
    expect($modification->expires_at)->not->toBeNull();
    expect($modification->vendor_explanation['en'])->toBe('Setup fee');

    $bookingVendor = BookingVendor::find($this->data['bookingVendor']->id);
    expect($bookingVendor->sub_status)->toBe(VendorSubStatus::Modified);
    expect($bookingVendor->responded_at)->not->toBeNull();

    $booking = Booking::find($this->data['booking']->id);
    expect($booking->lifecycle_status->getValue())->toBe(LifecycleStatus::CustomerReview->value);

    Event::assertDispatched(VendorModificationProposed::class);
})->group('booking', 'modification-builder', 'negotiation');

it('writes a draft→pending state transition for the modification', function (): void {
    app(SubmitBookingModificationProposalAction::class)->execute(
        new SubmitBookingModificationProposalDTO(
            bookingModificationId: $this->draft->id,
            vendorProfileId: $this->data['vendor']->id,
            proposedByUserId: $this->data['vendor']->user->id,
            vendorExplanation: ['en' => 'Setup fee'],
        ),
    );

    $transition = StateTransition::query()
        ->where('transitionable_type', BookingModification::class)
        ->where('transitionable_id', $this->draft->id)
        ->where('to_state', ModificationStatus::Pending->value)
        ->first();

    expect($transition)->not->toBeNull();
    expect($transition->from_state)->toBe(ModificationStatus::Draft->value);
})->group('booking', 'modification-builder');

it('refuses when vendor explanation is empty in both locales', function (): void {
    expect(fn () => app(SubmitBookingModificationProposalAction::class)->execute(
        new SubmitBookingModificationProposalDTO(
            bookingModificationId: $this->draft->id,
            vendorProfileId: $this->data['vendor']->id,
            proposedByUserId: $this->data['vendor']->user->id,
            vendorExplanation: ['en' => '', 'ar' => ''],
        ),
    ))->toThrow(HttpException::class);
})->group('booking', 'modification-builder');

it('refuses when the draft has no items', function (): void {
    $emptyDraft = app(CreateBookingModificationAction::class)->execute(new CreateBookingModificationDTO(
        bookingVendorId: $this->data['bookingVendor']->id,
        vendorProfileId: $this->data['vendor']->id,
        proposedByUserId: $this->data['vendor']->user->id,
    ));

    // Drop the previously-added item by deleting the draft and creating a fresh one
    expect($emptyDraft->id)->toBe($this->draft->id); // idempotency confirms same draft
    $this->draft->items()->delete();

    expect(fn () => app(SubmitBookingModificationProposalAction::class)->execute(
        new SubmitBookingModificationProposalDTO(
            bookingModificationId: $this->draft->id,
            vendorProfileId: $this->data['vendor']->id,
            proposedByUserId: $this->data['vendor']->user->id,
            vendorExplanation: ['en' => 'Hello'],
        ),
    ))->toThrow(HttpException::class);
})->group('booking', 'modification-builder');
