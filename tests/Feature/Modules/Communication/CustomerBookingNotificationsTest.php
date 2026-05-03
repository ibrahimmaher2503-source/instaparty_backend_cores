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
    // Bind stub adapters to prevent real HTTP calls
    $this->app->bind('push_adapter', fn () => new class implements NotificationChannelAdapter
    {
        public function send(NotificationDispatch $dispatch): void
        {
            $dispatch->status = DispatchStatus::Sent;
            $dispatch->provider = 'stub_push';
            $dispatch->sent_at = now();
            $dispatch->save();
        }
    });
    $this->app->bind('email_adapter', fn () => new class implements NotificationChannelAdapter
    {
        public function send(NotificationDispatch $dispatch): void
        {
            $dispatch->status = DispatchStatus::Sent;
            $dispatch->provider = 'stub_email';
            $dispatch->sent_at = now();
            $dispatch->save();
        }
    });
    $this->app->bind('sms_adapter', fn () => new class implements NotificationChannelAdapter
    {
        public function send(NotificationDispatch $dispatch): void
        {
            $dispatch->status = DispatchStatus::Sent;
            $dispatch->provider = 'stub_sms';
            $dispatch->sent_at = now();
            $dispatch->save();
        }
    });
});

function custBkCreatePushEmailTemplates(string $eventKey, string $audience = 'customer'): void
{
    foreach (['push', 'email'] as $channel) {
        NotificationTemplate::create([
            'public_id' => Str::ulid()->toBase32(),
            'event_key' => $eventKey,
            'channel' => $channel,
            'audience' => $audience,
            'body' => ['en' => "EN body for {$eventKey}", 'ar' => "AR body لـ {$eventKey}"],
            'subject' => ['en' => "EN subject for {$eventKey}", 'ar' => "AR subject لـ {$eventKey}"],
            'is_active' => true,
        ]);
    }
}

it('PaymentCaptured: dispatches push + email dispatch rows for customer', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);
    custBkCreatePushEmailTemplates('payment.captured');

    $action = app(DispatchNotificationAction::class);

    foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
        $action->execute(new DispatchNotificationDTO(
            eventKey: 'payment.captured',
            channel: $channel,
            audience: NotificationAudience::Customer,
            eventCategory: EventCategory::Payment,
            userId: $user->id,
            context: ['booking_id' => 'B123', 'amount' => '100 EGP'],
        ));
    }

    expect(NotificationDispatch::where('user_id', $user->id)->count())->toBe(2);
})->group('communication');

it('dispatch row locale matches user preferred_locale (ar)', function () {
    $user = User::factory()->create(['preferred_locale' => 'ar']);
    custBkCreatePushEmailTemplates('payment.captured');

    $action = app(DispatchNotificationAction::class);
    $action->execute(new DispatchNotificationDTO(
        eventKey: 'payment.captured',
        channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Payment,
        userId: $user->id,
        context: [],
    ));

    $dispatch = NotificationDispatch::where('user_id', $user->id)->first();
    expect($dispatch)->not()->toBeNull()
        ->and($dispatch->locale)->toBe('ar');
})->group('communication');

it('dispatch row locale matches user preferred_locale (en)', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);
    custBkCreatePushEmailTemplates('payment.captured');

    $action = app(DispatchNotificationAction::class);
    $action->execute(new DispatchNotificationDTO(
        eventKey: 'payment.captured',
        channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Payment,
        userId: $user->id,
        context: [],
    ));

    $dispatch = NotificationDispatch::where('user_id', $user->id)->first();
    expect($dispatch->locale)->toBe('en');
})->group('communication');

it('missing template writes failed dispatch row, does not throw', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);

    $action = app(DispatchNotificationAction::class);
    $action->execute(new DispatchNotificationDTO(
        eventKey: 'booking.modified',
        channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Booking,
        userId: $user->id,
        context: [],
    ));

    $dispatch = NotificationDispatch::where('user_id', $user->id)->first();
    expect($dispatch)->not()->toBeNull()
        ->and($dispatch->status)->toBe(DispatchStatus::Failed)
        ->and($dispatch->error_message)->not()->toBeNull();
})->group('communication');

it('BookingModified: context contains booking public_id', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);
    custBkCreatePushEmailTemplates('booking.modified');

    $action = app(DispatchNotificationAction::class);
    $action->execute(new DispatchNotificationDTO(
        eventKey: 'booking.modified',
        channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Booking,
        userId: $user->id,
        context: ['booking_id' => 'BK-PUBLIC-001'],
    ));

    $dispatch = NotificationDispatch::where('user_id', $user->id)->first();
    expect($dispatch)->not()->toBeNull()
        ->and($dispatch->context['booking_id'])->toBe('BK-PUBLIC-001');
})->group('communication');
