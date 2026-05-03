<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\CreateCampaignAction;
use App\Modules\Communication\Application\Actions\DispatchCampaignAction;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\BuildCampaignDTO;
use App\Modules\Communication\Application\Jobs\DispatchCampaignRecipientJob;
use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Identity\Database\Factories\UserFactory;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function runSingleRecipientCampaign(CampaignChannel $channel, string $productType): array
{
    $admin = UserFactory::new()->admin()->create();
    $customer = UserFactory::new()->customer()->create(['preferred_locale' => 'en', 'status' => 'active']);

    createBookingForUser($customer->id, $productType, now()->subDays(5));

    $campaign = app(CreateCampaignAction::class)->execute(new BuildCampaignDTO(
        name: "Channel test: {$channel->value}",
        channel: $channel,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: ['booked_product_type' => $productType, 'booked_within_days' => 30],
        body: ['en' => 'Test body', 'ar' => 'نص اختبار'],
        subject: ['en' => 'Test subject', 'ar' => 'موضوع'],
        createdBy: $admin->id,
    ));

    app(DispatchCampaignAction::class)->execute($campaign);
    $run = $campaign->runs()->first();

    $job = new DispatchCampaignRecipientJob($campaign->id, $run->id, $customer->id);
    $job->handle(app(DispatchNotificationAction::class));

    $recipient = CampaignRecipient::where('campaign_run_id', $run->id)
        ->where('user_id', $customer->id)
        ->first();

    $dispatch = $recipient->dispatch_id ? NotificationDispatch::find($recipient->dispatch_id) : null;

    return [$recipient, $dispatch];
}

it('push campaign routes dispatch through notification_dispatches with channel push', function () {
    [$recipient, $dispatch] = runSingleRecipientCampaign(CampaignChannel::Push, 'rental');

    expect($recipient->status)->toBe(CampaignRecipientStatus::Sent)
        ->and($recipient->dispatch_id)->not->toBeNull();

    expect($dispatch)->not->toBeNull()
        ->and($dispatch->channel)->toBe(NotificationChannel::Push);
})->group('communication');

it('sms campaign routes dispatch through notification_dispatches with channel sms', function () {
    [$recipient, $dispatch] = runSingleRecipientCampaign(CampaignChannel::Sms, 'rental');

    expect($recipient->status)->toBe(CampaignRecipientStatus::Sent)
        ->and($recipient->dispatch_id)->not->toBeNull();

    expect($dispatch)->not->toBeNull()
        ->and($dispatch->channel)->toBe(NotificationChannel::Sms);
})->group('communication');

it('whatsapp campaign routes dispatch through notification_dispatches with channel whatsapp', function () {
    [$recipient, $dispatch] = runSingleRecipientCampaign(CampaignChannel::WhatsApp, 'sale');

    expect($recipient->status)->toBe(CampaignRecipientStatus::Sent)
        ->and($recipient->dispatch_id)->not->toBeNull();

    expect($dispatch)->not->toBeNull()
        ->and($dispatch->channel)->toBe(NotificationChannel::Whatsapp);
})->group('communication');

it('email campaign routes dispatch through notification_dispatches with channel email', function () {
    [$recipient, $dispatch] = runSingleRecipientCampaign(CampaignChannel::Email, 'digital');

    expect($recipient->status)->toBe(CampaignRecipientStatus::Sent)
        ->and($recipient->dispatch_id)->not->toBeNull();

    expect($dispatch)->not->toBeNull()
        ->and($dispatch->channel)->toBe(NotificationChannel::Email);
})->group('communication');

it('campaign_recipients dispatch_id links to correct notification_dispatches row', function () {
    [$recipient, $dispatch] = runSingleRecipientCampaign(CampaignChannel::Push, 'rental');

    expect($recipient->dispatch_id)->toBe($dispatch->id);
})->group('communication');
