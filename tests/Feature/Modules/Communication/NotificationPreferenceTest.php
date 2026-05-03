<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationPreference;
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
    $this->app->bind('email_adapter', fn () => $stubAdapter);
    $this->app->bind('sms_adapter', fn () => $stubAdapter);
});

it('marketing opt-out: no push dispatch created for marketing event', function () {
    $user = \App\Modules\Identity\Domain\Models\User::factory()->create();
    NotificationPreference::create([
        'public_id'      => Str::ulid()->toBase32(),
        'user_id'        => $user->id,
        'channel'        => NotificationChannel::Push->value,
        'event_category' => EventCategory::Marketing->value,
        'is_enabled'     => false,
    ]);

    NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'marketing.promo',
        'channel'   => 'push',
        'audience'  => 'customer',
        'body'      => ['en' => 'Promo!', 'ar' => 'عرض!'],
        'is_active' => true,
    ]);

    $action = app(DispatchNotificationAction::class);
    $action->execute(new DispatchNotificationDTO(
        eventKey: 'marketing.promo',
        channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Marketing,
        userId: $user->id,
        context: [],
    ));

    expect(NotificationDispatch::where('user_id', $user->id)->count())->toBe(0);
})->group('communication');

it('marketing opt-out does not block booking category dispatches', function () {
    $user = \App\Modules\Identity\Domain\Models\User::factory()->create();
    NotificationPreference::create([
        'public_id'      => Str::ulid()->toBase32(),
        'user_id'        => $user->id,
        'channel'        => NotificationChannel::Push->value,
        'event_category' => EventCategory::Marketing->value,
        'is_enabled'     => false,
    ]);

    NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'booking.submitted',
        'channel'   => 'push',
        'audience'  => 'customer',
        'body'      => ['en' => 'Booking!', 'ar' => 'حجز!'],
        'is_active' => true,
    ]);

    $action = app(DispatchNotificationAction::class);
    $action->execute(new DispatchNotificationDTO(
        eventKey: 'booking.submitted',
        channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Booking,
        userId: $user->id,
        context: [],
    ));

    expect(NotificationDispatch::where('user_id', $user->id)->count())->toBe(1);
})->group('communication');

it('missing preference row defaults to enabled (dispatch proceeds)', function () {
    $user = \App\Modules\Identity\Domain\Models\User::factory()->create();

    NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'booking.submitted',
        'channel'   => 'push',
        'audience'  => 'customer',
        'body'      => ['en' => 'Booking!', 'ar' => 'حجز!'],
        'is_active' => true,
    ]);

    $action = app(DispatchNotificationAction::class);
    $action->execute(new DispatchNotificationDTO(
        eventKey: 'booking.submitted',
        channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Booking,
        userId: $user->id,
        context: [],
    ));

    expect(NotificationDispatch::where('user_id', $user->id)->count())->toBe(1);
})->group('communication');
