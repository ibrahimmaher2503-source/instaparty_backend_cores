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

it('SalePreparationStarted produces failed dispatch when only rental.delivery_scheduled template exists', function () {
    $user = User::factory()->create();

    // Only seed rental template — NOT sale
    NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'rental.delivery_scheduled',
        'channel' => 'push',
        'audience' => 'customer',
        'body' => ['en' => 'Rental delivery scheduled.', 'ar' => 'تم جدولة تسليم الإيجار.'],
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
        ->and($dispatch->status)->toBe(DispatchStatus::Failed)
        ->and($dispatch->error_message)->toContain('sale.preparation_started');
})->group('communication', 'sale');

it('all 4 per-type event keys resolve to distinct template_ids', function () {
    $user = User::factory()->create();

    $eventKeys = [
        'rental.delivery_scheduled',
        'sale.preparation_started',
        'digital.delivered',
        'digital.expiring_soon',
    ];

    $templateIds = [];
    foreach ($eventKeys as $eventKey) {
        $template = NotificationTemplate::create([
            'public_id' => Str::ulid()->toBase32(),
            'event_key' => $eventKey,
            'channel' => 'push',
            'audience' => 'customer',
            'body' => ['en' => "Body for {$eventKey}", 'ar' => "نص لـ {$eventKey}"],
            'is_active' => true,
        ]);
        $templateIds[$eventKey] = $template->id;
    }

    // All 4 IDs should be unique
    expect(count(array_unique(array_values($templateIds))))->toBe(4);

    $action = app(DispatchNotificationAction::class);
    foreach ($eventKeys as $eventKey) {
        $action->execute(new DispatchNotificationDTO(
            eventKey: $eventKey,
            channel: NotificationChannel::Push,
            audience: NotificationAudience::Customer,
            eventCategory: EventCategory::Booking,
            userId: $user->id,
            context: [],
        ));
    }

    $dispatches = NotificationDispatch::where('user_id', $user->id)->get();
    expect($dispatches)->toHaveCount(4);
    $resolvedTemplateIds = $dispatches->pluck('notification_template_id')->unique()->values();
    expect($resolvedTemplateIds)->toHaveCount(4);
})->group('communication', 'rental', 'sale', 'digital');
