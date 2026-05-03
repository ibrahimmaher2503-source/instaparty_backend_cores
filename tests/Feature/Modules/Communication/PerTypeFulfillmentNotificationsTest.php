<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    NotificationTemplate::query()->delete();
    $stubAdapter = new class implements NotificationChannelAdapter
    {
        public function send(NotificationDispatch $dispatch): void
        {
            $dispatch->status = DispatchStatus::Sent;
            $dispatch->sent_at = now();
            $dispatch->save();
        }
    };
    $this->app->bind('push_adapter', fn () => $stubAdapter);
    $this->app->bind('sms_adapter', fn () => $stubAdapter);
    $this->app->bind('email_adapter', fn () => $stubAdapter);
});

it('rental: RentalDeliveryScheduled dispatches push + sms using rental.delivery_scheduled template', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);

    foreach (['push', 'sms'] as $channel) {
        NotificationTemplate::create([
            'public_id' => Str::ulid()->toBase32(),
            'event_key' => 'rental.delivery_scheduled',
            'channel' => $channel,
            'audience' => 'customer',
            'body' => ['en' => 'Delivery scheduled.', 'ar' => 'تم جدولة التسليم.'],
            'is_active' => true,
        ]);
    }

    $action = app(DispatchNotificationAction::class);
    foreach ([NotificationChannel::Push, NotificationChannel::Sms] as $channel) {
        $action->execute(new DispatchNotificationDTO(
            eventKey: 'rental.delivery_scheduled',
            channel: $channel,
            audience: NotificationAudience::Customer,
            eventCategory: EventCategory::Booking,
            userId: $user->id,
            context: ['booking_id' => 'R001'],
        ));
    }

    $dispatches = NotificationDispatch::where('user_id', $user->id)->get();
    expect($dispatches)->toHaveCount(2);
    $channels = $dispatches->pluck('channel')->map->value->sort()->values()->toArray();
    expect($channels)->toContain('push')->toContain('sms');
})->group('communication', 'rental');

it('sale: SalePreparationStarted dispatches push using sale.preparation_started template', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);

    NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'sale.preparation_started',
        'channel' => 'push',
        'audience' => 'customer',
        'body' => ['en' => 'Preparation started.', 'ar' => 'بدأ التحضير.'],
        'is_active' => true,
    ]);

    $action = app(DispatchNotificationAction::class);
    $action->execute(new DispatchNotificationDTO(
        eventKey: 'sale.preparation_started',
        channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Booking,
        userId: $user->id,
        context: [],
    ));

    $dispatch = NotificationDispatch::where('user_id', $user->id)->first();
    expect($dispatch)->not()->toBeNull()
        ->and($dispatch->channel)->toBe(NotificationChannel::Push);
})->group('communication', 'sale');

it('digital: DigitalDelivered dispatches push + email using digital.delivered template', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);

    foreach (['push', 'email'] as $channel) {
        NotificationTemplate::create([
            'public_id' => Str::ulid()->toBase32(),
            'event_key' => 'digital.delivered',
            'channel' => $channel,
            'audience' => 'customer',
            'body' => ['en' => 'Digital delivered.', 'ar' => 'تم التسليم الرقمي.'],
            'is_active' => true,
        ]);
    }

    $action = app(DispatchNotificationAction::class);
    foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
        $action->execute(new DispatchNotificationDTO(
            eventKey: 'digital.delivered',
            channel: $channel,
            audience: NotificationAudience::Customer,
            eventCategory: EventCategory::Booking,
            userId: $user->id,
            context: [],
        ));
    }

    expect(NotificationDispatch::where('user_id', $user->id)->count())->toBe(2);
})->group('communication', 'digital');

it('digital: DigitalExpiringSoon context contains expiry_date', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);

    NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'digital.expiring_soon',
        'channel' => 'push',
        'audience' => 'customer',
        'body' => ['en' => 'Expiring soon.', 'ar' => 'ينتهي قريباً.'],
        'is_active' => true,
    ]);

    $action = app(DispatchNotificationAction::class);
    $action->execute(new DispatchNotificationDTO(
        eventKey: 'digital.expiring_soon',
        channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Booking,
        userId: $user->id,
        context: ['expiry_date' => '2026-06-15'],
    ));

    $dispatch = NotificationDispatch::where('user_id', $user->id)->first();
    expect($dispatch)->not()->toBeNull()
        ->and($dispatch->context['expiry_date'])->toBe('2026-06-15');
})->group('communication', 'digital');
