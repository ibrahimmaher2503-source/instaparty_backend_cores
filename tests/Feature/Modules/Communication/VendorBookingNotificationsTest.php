<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use Illuminate\Support\Str;


beforeEach(function () {
        \App\Modules\Communication\Domain\Models\NotificationTemplate::query()->delete();
    $stubAdapter = new class implements \App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter {
        public function send(NotificationDispatch $dispatch): void
        {
            $dispatch->status = DispatchStatus::Sent;
            $dispatch->sent_at = now();
            $dispatch->save();
        }
    };
    $this->app->bind('push_adapter', fn () => $stubAdapter);
    $this->app->bind('sms_adapter', fn () => $stubAdapter);
});

it('vendor receives push + sms on BookingSubmitted event', function () {
    $vendor = \App\Modules\Identity\Domain\Models\User::factory()->create(['preferred_locale' => 'ar']);

    foreach (['push', 'sms'] as $channel) {
        NotificationTemplate::create([
            'public_id' => Str::ulid()->toBase32(),
            'event_key' => 'booking.submitted',
            'channel'   => $channel,
            'audience'  => 'vendor',
            'body'      => ['en' => 'New booking request', 'ar' => 'طلب حجز جديد'],
            'is_active' => true,
        ]);
    }

    $action = app(DispatchNotificationAction::class);
    foreach ([NotificationChannel::Push, NotificationChannel::Sms] as $channel) {
        $action->execute(new DispatchNotificationDTO(
            eventKey: 'booking.submitted',
            channel: $channel,
            audience: NotificationAudience::Vendor,
            eventCategory: EventCategory::Booking,
            userId: $vendor->id,
            context: ['booking_id' => 'B001'],
        ));
    }

    $dispatches = NotificationDispatch::where('user_id', $vendor->id)->get();
    expect($dispatches)->toHaveCount(2);
    $channels = $dispatches->pluck('channel')->map->value->sort()->values()->toArray();
    expect($channels)->toContain('push')->toContain('sms');
})->group('communication');
