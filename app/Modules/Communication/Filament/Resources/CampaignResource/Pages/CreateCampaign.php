<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\CampaignResource\Pages;

use App\Modules\Communication\Application\Actions\CreateCampaignAction;
use App\Modules\Communication\Application\DTOs\BuildCampaignDTO;
use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Communication\Filament\Resources\CampaignResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $dto = new BuildCampaignDTO(
            name: $data['name'],
            channel: CampaignChannel::from($data['channel']),
            targetLocale: CampaignTargetLocale::from($data['target_locale']),
            segmentFilters: $data['segment_filters'] ?? [],
            body: $data['body'] ?? [],
            subject: $data['subject'] ?? [],
            createdBy: auth()->id(),
        );

        return app(CreateCampaignAction::class)->execute($dto);
    }
}
