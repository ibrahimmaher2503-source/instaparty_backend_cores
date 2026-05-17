<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\VendorAcceptBookingAction;
use App\Modules\Booking\Application\Actions\VendorModifyBookingAction;
use App\Modules\Booking\Application\Actions\VendorRejectBookingAction;
use App\Modules\Booking\Application\DTOs\VendorAcceptDTO;
use App\Modules\Booking\Application\DTOs\VendorModifyDTO;
use App\Modules\Booking\Application\DTOs\VendorRejectDTO;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * A minimal VendorModifyDTO with a single "add" change so the action can
 * build its diff snapshot without requiring any real BookingItems.
 */
function makeModifyDto(int $bookingVendorId, int $vendorProfileId, int $userId): VendorModifyDTO
{
    return new VendorModifyDTO(
        bookingVendorId: $bookingVendorId,
        vendorProfileId: $vendorProfileId,
        proposedByUserId: $userId,
        proposalKind: ModificationProposalKind::AddItem,
        changes: [
            [
                'change_kind' => 'add',
                'payload' => [
                    'unit_price_minor' => 10000,
                    'unit_price_currency' => 'EGP',
                    'quantity' => 1,
                ],
            ],
        ],
        vendorExplanation: null,
        idempotencyKey: null,
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// VendorAcceptBookingAction — deadline guard
// ─────────────────────────────────────────────────────────────────────────────

it('VendorAcceptBookingAction throws ResponseDeadlineExpiredException when deadline is in the past', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => now()->subHours(1),
    ]);

    $dto = new VendorAcceptDTO(
        bookingVendorId: $bookingVendor->id,
        vendorProfileId: $bookingVendor->vendor_profile_id,
        proposedByUserId: $bookingVendor->vendor_profile_id,
    );

    expect(fn () => app(VendorAcceptBookingAction::class)->execute($dto))
        ->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'deadline-guard');

it('VendorAcceptBookingAction does not throw ResponseDeadlineExpiredException when deadline is null', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => null,
    ]);

    $dto = new VendorAcceptDTO(
        bookingVendorId: $bookingVendor->id,
        vendorProfileId: $bookingVendor->vendor_profile_id,
        proposedByUserId: $bookingVendor->vendor_profile_id,
    );

    expect(fn () => app(VendorAcceptBookingAction::class)->execute($dto))
        ->not->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'deadline-guard');

it('VendorAcceptBookingAction does not throw ResponseDeadlineExpiredException when deadline is in the future', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => now()->addHours(2),
    ]);

    $dto = new VendorAcceptDTO(
        bookingVendorId: $bookingVendor->id,
        vendorProfileId: $bookingVendor->vendor_profile_id,
        proposedByUserId: $bookingVendor->vendor_profile_id,
    );

    expect(fn () => app(VendorAcceptBookingAction::class)->execute($dto))
        ->not->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'deadline-guard');

// ─────────────────────────────────────────────────────────────────────────────
// VendorRejectBookingAction — deadline guard
// ─────────────────────────────────────────────────────────────────────────────

it('VendorRejectBookingAction throws ResponseDeadlineExpiredException when deadline is in the past', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => now()->subHours(1),
    ]);

    $dto = new VendorRejectDTO(
        bookingVendorId: $bookingVendor->id,
        vendorProfileId: $bookingVendor->vendor_profile_id,
        proposedByUserId: $bookingVendor->vendor_profile_id,
    );

    expect(fn () => app(VendorRejectBookingAction::class)->execute($dto))
        ->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'deadline-guard');

it('VendorRejectBookingAction does not throw ResponseDeadlineExpiredException when deadline is null', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => null,
    ]);

    $dto = new VendorRejectDTO(
        bookingVendorId: $bookingVendor->id,
        vendorProfileId: $bookingVendor->vendor_profile_id,
        proposedByUserId: $bookingVendor->vendor_profile_id,
    );

    expect(fn () => app(VendorRejectBookingAction::class)->execute($dto))
        ->not->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'deadline-guard');

it('VendorRejectBookingAction does not throw ResponseDeadlineExpiredException when deadline is in the future', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => now()->addHours(2),
    ]);

    $dto = new VendorRejectDTO(
        bookingVendorId: $bookingVendor->id,
        vendorProfileId: $bookingVendor->vendor_profile_id,
        proposedByUserId: $bookingVendor->vendor_profile_id,
    );

    expect(fn () => app(VendorRejectBookingAction::class)->execute($dto))
        ->not->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'deadline-guard');

// ─────────────────────────────────────────────────────────────────────────────
// VendorModifyBookingAction — deadline guard
// ─────────────────────────────────────────────────────────────────────────────

it('VendorModifyBookingAction throws ResponseDeadlineExpiredException when deadline is in the past', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => now()->subHours(1),
    ]);

    $dto = makeModifyDto(
        bookingVendorId: $bookingVendor->id,
        vendorProfileId: $bookingVendor->vendor_profile_id,
        userId: $bookingVendor->vendor_profile_id,
    );

    expect(fn () => app(VendorModifyBookingAction::class)->execute($dto))
        ->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'deadline-guard');

it('VendorModifyBookingAction does not throw ResponseDeadlineExpiredException when deadline is null', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => null,
    ]);

    $dto = makeModifyDto(
        bookingVendorId: $bookingVendor->id,
        vendorProfileId: $bookingVendor->vendor_profile_id,
        userId: $bookingVendor->vendor_profile_id,
    );

    expect(fn () => app(VendorModifyBookingAction::class)->execute($dto))
        ->not->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'deadline-guard');

it('VendorModifyBookingAction does not throw ResponseDeadlineExpiredException when deadline is in the future', function (): void {
    $bookingVendor = BookingVendorFactory::new()->pending()->create([
        'response_deadline' => now()->addHours(2),
    ]);

    $dto = makeModifyDto(
        bookingVendorId: $bookingVendor->id,
        vendorProfileId: $bookingVendor->vendor_profile_id,
        userId: $bookingVendor->vendor_profile_id,
    );

    expect(fn () => app(VendorModifyBookingAction::class)->execute($dto))
        ->not->toThrow(ResponseDeadlineExpiredException::class);
})->group('booking', 'deadline-guard');
