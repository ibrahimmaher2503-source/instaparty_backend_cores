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
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Identity\Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('records opted-out recipient as skipped with no dispatch', function () {
    $admin = UserFactory::new()->admin()->create();
    $customer = UserFactory::new()->customer()->create(['preferred_locale' => 'en', 'status' => 'active']);

    createBookingForUser($customer->id, 'rental', now()->subDays(5));

    // Opt out from push marketing
    DB::table('notification_preferences')->insert([
        'user_id' => $customer->id,
        'channel' => 'push',
        'event_category' => 'marketing',
        'is_enabled' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $campaign = app(CreateCampaignAction::class)->execute(new BuildCampaignDTO(
        name: 'Opt-out test',
        channel: CampaignChannel::Push,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: ['booked_product_type' => 'rental', 'booked_within_days' => 30],
        body: ['en' => 'test en', 'ar' => 'اختبار'],
        subject: [],
        createdBy: $admin->id,
    ));

    app(DispatchCampaignAction::class)->execute($campaign);

    // Process the queued job
    Queue::assertPushed(DispatchCampaignRecipientJob::class);
    Queue::fake(); // reset

    // Manually run the job
    $run = $campaign->runs()->first();
    $job = new DispatchCampaignRecipientJob($campaign->id, $run->id, $customer->id);
    $job->handle(app(DispatchNotificationAction::class));

    $recipient = CampaignRecipient::where('campaign_run_id', $run->id)
        ->where('user_id', $customer->id)
        ->first();

    expect($recipient->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($recipient->dispatch_id)->toBeNull();
})->group('communication', 'campaign');

it('dispatches recipient with no preference row (default enabled)', function () {
    $admin = UserFactory::new()->admin()->create();
    $customer = UserFactory::new()->customer()->create(['preferred_locale' => 'en', 'status' => 'active']);

    createBookingForUser($customer->id, 'rental', now()->subDays(5));

    $campaign = app(CreateCampaignAction::class)->execute(new BuildCampaignDTO(
        name: 'Default enabled test',
        channel: CampaignChannel::Push,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: ['booked_product_type' => 'rental', 'booked_within_days' => 30],
        body: ['en' => 'body en', 'ar' => 'نص'],
        subject: [],
        createdBy: $admin->id,
    ));

    app(DispatchCampaignAction::class)->execute($campaign);

    $run = $campaign->runs()->first();
    $recipient = CampaignRecipient::where('campaign_run_id', $run->id)
        ->where('user_id', $customer->id)
        ->first();

    expect($recipient->status)->toBe(CampaignRecipientStatus::Queued);
})->group('communication', 'campaign');
