<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\BuildCampaignDTO;
use App\Modules\Communication\Application\Services\InvalidSegmentFilterException;
use App\Modules\Communication\Application\Services\SegmentResolver;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Models\Campaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCampaignAction
{
    public function __construct(
        private readonly SegmentResolver $segmentResolver,
    ) {}

    public function execute(BuildCampaignDTO $dto): Campaign
    {
        $this->validate($dto);

        return DB::transaction(function () use ($dto): Campaign {
            return Campaign::create([
                'public_id' => Str::ulid()->toBase32(),
                'name' => $dto->name,
                'channel' => $dto->channel->value,
                'target_locale' => $dto->targetLocale->value,
                'segment_filters' => $dto->segmentFilters,
                'product_type_segment' => $dto->productTypeSegment,
                'subject' => $dto->subject ?: null,
                'body' => $dto->body,
                'status' => CampaignStatus::Draft->value,
                'created_by' => $dto->createdBy,
            ]);
        });
    }

    private function validate(BuildCampaignDTO $dto): void
    {
        if (empty($dto->body['en'] ?? '') || empty($dto->body['ar'] ?? '')) {
            throw new \InvalidArgumentException('Campaign body must have non-empty English and Arabic translations.');
        }

        // Validate segment filters (throws InvalidSegmentFilterException on empty/unknown keys)
        // We pass 'both' for targetLocale validation since we're just checking filter keys here
        $this->segmentResolver->resolve($dto->segmentFilters, 'both');
    }
}
