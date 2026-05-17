<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\CreateBookingModificationAction;
use App\Modules\Booking\Application\Actions\DiscardBookingModificationDraftAction;
use App\Modules\Booking\Application\DTOs\CreateBookingModificationDTO;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../../BookingTestHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    $this->data = makeSubmittedBookingWithVendor();
});

it('hard-deletes a draft modification and its items', function (): void {
    $draft = app(CreateBookingModificationAction::class)->execute(new CreateBookingModificationDTO(
        bookingVendorId: $this->data['bookingVendor']->id,
        vendorProfileId: $this->data['vendor']->id,
        proposedByUserId: $this->data['vendor']->user->id,
    ));

    BookingModificationItem::create([
        'booking_modification_id' => $draft->id,
        'target_booking_item_id' => $this->data['item']->id,
        'change_kind' => ModificationChangeKind::Update,
        'payload' => ['unit_price_minor' => 60000, 'change_type' => 'change_price'],
    ]);

    app(DiscardBookingModificationDraftAction::class)->execute(
        $draft,
        $this->data['vendor']->user->id,
        $this->data['vendor']->id,
    );

    expect(BookingModification::find($draft->id))->toBeNull();
    expect(BookingModificationItem::where('booking_modification_id', $draft->id)->count())->toBe(0);

    // Audit trail preserved
    $audit = DB::table('audit_logs')
        ->where('auditable_type', BookingModification::class)
        ->where('auditable_id', $draft->id)
        ->where('action', 'modification.draft_discarded')
        ->first();
    expect($audit)->not->toBeNull();
})->group('booking', 'modification-builder');

it('refuses to discard a non-draft modification', function (): void {
    $data = makeBookingWithPendingModification();

    expect(fn () => app(DiscardBookingModificationDraftAction::class)->execute(
        $data['modification'],
        $data['vendor']->user->id,
        $data['vendor']->id,
    ))->toThrow(HttpException::class);

    expect($data['modification']->fresh()->status)->toBe(ModificationStatus::Pending);
})->group('booking', 'modification-builder');
