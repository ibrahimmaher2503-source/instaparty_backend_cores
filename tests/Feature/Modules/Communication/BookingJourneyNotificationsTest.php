<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\AlternativeVendorProposed;
use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Events\VendorRejected;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Communication\Application\Listeners\OnAlternativeVendorProposed;
use App\Modules\Communication\Application\Listeners\OnBookingCancelled;
use App\Modules\Communication\Application\Listeners\OnVendorRejected;
use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Str;

/**
 * Gap-closure Phase 3 P0 — the missing customer-journey notification events:
 * booking.rejected, booking.cancelled, booking.alternative.
 *
 * NOTE: notification_dispatches has no populated event_key column on this
 * dispatch path — rows link to their event via notification_template_id.
 */
beforeEach(function (): void {
    NotificationTemplate::query()->delete();

    $stubAdapter = new class implements NotificationChannelAdapter
    {
        public function name(): string
        {
            return 'test_stub';
        }

        public function healthCheck(): ProviderHealthResult
        {
            return ProviderHealthResult::reachable('test_stub', 0);
        }

        public function send(NotificationDispatch $dispatch): void
        {
            $dispatch->status = DispatchStatus::Sent;
            $dispatch->sent_at = now();
            $dispatch->save();
        }
    };
    $this->app->bind('push_adapter', fn () => $stubAdapter);
    $this->app->bind('email_adapter', fn () => $stubAdapter);

    $this->templateIds = [];
    foreach ([
        ['booking.rejected', 'push', 'customer'],
        ['booking.rejected', 'email', 'customer'],
        ['booking.cancelled', 'push', 'customer'],
        ['booking.cancelled', 'email', 'customer'],
        ['booking.cancelled', 'push', 'vendor'],
        ['booking.alternative', 'push', 'customer'],
        ['booking.alternative', 'email', 'customer'],
    ] as [$eventKey, $channel, $audience]) {
        $template = NotificationTemplate::create([
            'public_id' => Str::ulid()->toBase32(),
            'event_key' => $eventKey,
            'channel' => $channel,
            'audience' => $audience,
            'body' => ['en' => 'Body for '.$eventKey, 'ar' => 'نص '.$eventKey],
            'is_active' => true,
        ]);
        $this->templateIds[$eventKey][] = $template->id;
    }

    $this->dispatchesFor = fn (string $eventKey, int $userId) => NotificationDispatch::query()
        ->where('user_id', $userId)
        ->whereIn('notification_template_id', $this->templateIds[$eventKey])
        ->get();
});

it('notifies the customer on vendor rejection', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $data['bookingVendor']->update([
        'sub_status' => VendorSubStatus::Rejected,
        'rejection_reason' => ['en' => 'Fully booked', 'ar' => 'محجوز بالكامل'],
    ]);

    app(OnVendorRejected::class)->handle(new VendorRejected($data['bookingVendor']));

    $dispatches = ($this->dispatchesFor)('booking.rejected', $data['customer']->id);

    expect($dispatches)->toHaveCount(2)
        ->and($dispatches->pluck('channel')->map->value->sort()->values()->all())->toBe(['email', 'push'])
        ->and($dispatches->first()->context['rejection_reason'] ?? null)->toBe('Fully booked');
})->group('communication', 'booking-journey');

it('notifies customer and engaged vendors on cancellation', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $data['bookingVendor']->update(['sub_status' => VendorSubStatus::Accepted]);

    app(OnBookingCancelled::class)->handle(new BookingCancelled($data['booking']));

    $customerDispatches = ($this->dispatchesFor)('booking.cancelled', $data['customer']->id);
    $vendorDispatches = ($this->dispatchesFor)('booking.cancelled', $data['vendor']->user_id);

    expect($customerDispatches)->toHaveCount(2)
        ->and($vendorDispatches)->toHaveCount(1)
        ->and($vendorDispatches->first()->channel->value)->toBe('push');
})->group('communication', 'booking-journey');

it('suppresses booking.cancelled when every vendor rejected', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $data['bookingVendor']->update(['sub_status' => VendorSubStatus::Rejected]);

    app(OnBookingCancelled::class)->handle(new BookingCancelled($data['booking']));

    expect(
        NotificationDispatch::whereIn('notification_template_id', $this->templateIds['booking.cancelled'])->count()
    )->toBe(0);
})->group('communication', 'booking-journey');

it('notifies the customer when admin proposes alternative vendors', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $admin = User::factory()->create();

    $intervention = BookingAdminIntervention::create([
        'public_id' => Str::ulid()->toBase32(),
        'booking_id' => $data['booking']->id,
        'admin_id' => $admin->id,
        'intervention_type' => InterventionType::AdminNote,
        'reason' => 'Suggesting alternatives',
        'before_state' => [],
        'after_state' => [],
    ]);

    app(OnAlternativeVendorProposed::class)
        ->handle(new AlternativeVendorProposed($data['booking'], $intervention));

    expect(($this->dispatchesFor)('booking.alternative', $data['customer']->id))->toHaveCount(2);
})->group('communication', 'booking-journey');
