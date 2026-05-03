<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;

final class BuildCampaignDTO
{
    /**
     * @param  array<string, mixed>  $segmentFilters
     * @param  array<string, string>  $body
     * @param  array<string, string>  $subject
     * @param  array<string, mixed>|null  $productTypeSegment
     */
    public function __construct(
        public readonly string $name,
        public readonly CampaignChannel $channel,
        public readonly CampaignTargetLocale $targetLocale,
        public readonly array $segmentFilters,
        public readonly array $body,
        public readonly array $subject,
        public readonly int $createdBy,
        public readonly ?array $productTypeSegment = null,
    ) {}
}
