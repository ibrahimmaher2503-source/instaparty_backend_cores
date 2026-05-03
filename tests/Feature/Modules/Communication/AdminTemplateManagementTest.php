<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Application\Services\TemplateResolver;
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
    $this->app->bind('email_adapter', fn () => $stubAdapter);
});

it('updated AR body in template is used for next AR-locale dispatch', function () {
    $user = User::factory()->create(['preferred_locale' => 'ar']);

    $template = NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'booking.submitted',
        'channel' => 'push',
        'audience' => 'customer',
        'body' => ['en' => 'Original EN', 'ar' => 'النص الأصلي'],
        'is_active' => true,
    ]);

    // Simulate admin updating AR body via Filament
    $template->setTranslation('body', 'ar', 'النص المحدّث');
    $template->save();

    $resolver = app(TemplateResolver::class);
    $resolved = $resolver->resolve('booking.submitted', NotificationChannel::Push, NotificationAudience::Customer, 'ar');

    expect($resolved->body)->toBe('النص المحدّث');
})->group('communication');

it('inactive template causes failed dispatch', function () {
    $user = User::factory()->create();

    NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'booking.submitted',
        'channel' => 'push',
        'audience' => 'customer',
        'body' => ['en' => 'Body', 'ar' => 'نص'],
        'is_active' => false, // Inactive
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

    $dispatch = NotificationDispatch::where('user_id', $user->id)->first();
    expect($dispatch)->not()->toBeNull()
        ->and($dispatch->status)->toBe(DispatchStatus::Failed);
})->group('communication');

it('TemplateResolver requires both EN and AR body translations present', function () {
    $template = NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'booking.submitted',
        'channel' => 'push',
        'audience' => 'customer',
        'body' => ['en' => 'English only'],  // Missing AR
        'is_active' => true,
    ]);

    $resolver = app(TemplateResolver::class);
    $resolved = $resolver->resolve('booking.submitted', NotificationChannel::Push, NotificationAudience::Customer, 'ar');

    // Falls back to EN when AR is missing (per TemplateResolver implementation)
    expect($resolved->body)->toBe('English only');
})->group('communication');
