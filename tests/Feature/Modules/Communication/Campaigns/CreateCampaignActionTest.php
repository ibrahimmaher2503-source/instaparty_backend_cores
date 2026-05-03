<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\CreateCampaignAction;
use App\Modules\Communication\Application\DTOs\BuildCampaignDTO;
use App\Modules\Communication\Application\Services\InvalidSegmentFilterException;
use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Identity\Database\Factories\UserFactory;

it('creates a campaign in draft state', function () {
    $admin = UserFactory::new()->admin()->create();

    $dto = new BuildCampaignDTO(
        name: '10% off Rentals',
        channel: CampaignChannel::Push,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: ['booked_product_type' => 'rental', 'booked_within_days' => 30],
        body: ['en' => '10% off rentals this week', 'ar' => 'خصم 10%'],
        subject: [],
        createdBy: $admin->id,
    );

    $campaign = app(CreateCampaignAction::class)->execute($dto);

    expect($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->name)->toBe('10% off Rentals')
        ->and($campaign->getTranslation('body', 'en'))->toBe('10% off rentals this week')
        ->and($campaign->getTranslation('body', 'ar'))->toBe('خصم 10%');
})->group('communication', 'campaign');

it('rejects a campaign with missing English body', function () {
    $admin = UserFactory::new()->admin()->create();

    $dto = new BuildCampaignDTO(
        name: 'Test',
        channel: CampaignChannel::Push,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: ['booked_within_days' => 30],
        body: ['en' => '', 'ar' => 'نص'],
        subject: [],
        createdBy: $admin->id,
    );

    expect(fn () => app(CreateCampaignAction::class)->execute($dto))
        ->toThrow(InvalidArgumentException::class);
})->group('communication', 'campaign');

it('rejects a campaign with missing Arabic body', function () {
    $admin = UserFactory::new()->admin()->create();

    $dto = new BuildCampaignDTO(
        name: 'Test',
        channel: CampaignChannel::Push,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: ['booked_within_days' => 30],
        body: ['en' => 'Body text', 'ar' => ''],
        subject: [],
        createdBy: $admin->id,
    );

    expect(fn () => app(CreateCampaignAction::class)->execute($dto))
        ->toThrow(InvalidArgumentException::class);
})->group('communication', 'campaign');

it('rejects a campaign with empty segment filters', function () {
    $admin = UserFactory::new()->admin()->create();

    $dto = new BuildCampaignDTO(
        name: 'Test',
        channel: CampaignChannel::Push,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: [],
        body: ['en' => 'Body', 'ar' => 'نص'],
        subject: [],
        createdBy: $admin->id,
    );

    expect(fn () => app(CreateCampaignAction::class)->execute($dto))
        ->toThrow(InvalidSegmentFilterException::class);
})->group('communication', 'campaign');
