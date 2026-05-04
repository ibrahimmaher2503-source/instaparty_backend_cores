<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\CampaignAlreadyDispatchedException;
use App\Modules\Communication\Application\Actions\CreateCampaignAction;
use App\Modules\Communication\Application\Actions\DispatchCampaignAction;
use App\Modules\Communication\Application\DTOs\BuildCampaignDTO;
use App\Modules\Communication\Application\Jobs\DispatchCampaignRecipientJob;
use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('dispatches campaign and creates run + recipient rows', function () {
    $admin = User::factory()->asAdmin()->create();

    $customers = User::factory()->asCustomer()->count(3)->create(['preferred_locale' => 'en', 'status' => 'active']);
    $others = User::factory()->asCustomer()->count(2)->create(['preferred_locale' => 'en', 'status' => 'active']);

    foreach ($customers as $c) {
        createBookingForUser($c->id, 'rental', now()->subDays(5));
    }

    $campaign = app(CreateCampaignAction::class)->execute(new BuildCampaignDTO(
        name: '10% off Rentals',
        channel: CampaignChannel::Push,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: ['booked_product_type' => 'rental', 'booked_within_days' => 30],
        body: ['en' => '10% off rentals this week', 'ar' => 'خصم 10%'],
        subject: [],
        createdBy: $admin->id,
    ));

    $run = app(DispatchCampaignAction::class)->execute($campaign);

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Running)
        ->and($run->recipients_total)->toBeGreaterThanOrEqual(3);

    $recipientIds = CampaignRecipient::where('campaign_run_id', $run->id)->pluck('user_id');

    foreach ($customers as $c) {
        expect($recipientIds)->toContain($c->id);
    }

    foreach ($others as $o) {
        expect($recipientIds)->not->toContain($o->id);
    }

    Queue::assertPushed(DispatchCampaignRecipientJob::class, $run->recipients_total);
})->group('communication', 'campaign');

it('immediately completes campaign when segment resolves to zero recipients', function () {
    $admin = User::factory()->asAdmin()->create();

    // Use a locale that no user will ever have to guarantee zero segment match
    $campaign = app(CreateCampaignAction::class)->execute(new BuildCampaignDTO(
        name: 'Empty segment',
        channel: CampaignChannel::Push,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: ['preferred_locale' => 'zz', 'booked_within_days' => 30],
        body: ['en' => 'body', 'ar' => 'نص'],
        subject: [],
        createdBy: $admin->id,
    ));

    $run = app(DispatchCampaignAction::class)->execute($campaign);

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Completed)
        ->and($run->fresh()->recipients_total)->toBe(0)
        ->and($run->fresh()->completed_at)->not->toBeNull();

    Queue::assertNothingPushed();
})->group('communication', 'campaign');

it('rejects dispatch when campaign is not in draft state', function () {
    $admin = User::factory()->asAdmin()->create();

    $campaign = app(CreateCampaignAction::class)->execute(new BuildCampaignDTO(
        name: 'Already running',
        channel: CampaignChannel::Push,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: ['booked_within_days' => 30],
        body: ['en' => 'body', 'ar' => 'نص'],
        subject: [],
        createdBy: $admin->id,
    ));

    $campaign->update(['status' => CampaignStatus::Running->value]);

    expect(fn () => app(DispatchCampaignAction::class)->execute($campaign))
        ->toThrow(CampaignAlreadyDispatchedException::class);
})->group('communication', 'campaign');
