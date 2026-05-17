<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Application\DTOs\OccasionDTO;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateOccasionAction
{
    use RecordsTaxonomyAudit;

    public function execute(Occasion $occasion, OccasionDTO $dto, ?User $actor = null): Occasion
    {
        return DB::transaction(function () use ($occasion, $dto, $actor): Occasion {
            $occasion->update($dto->toAttributes());

            $this->recordAudit($occasion, 'occasion_updated', $actor, $dto->toAttributes());

            return $occasion->refresh();
        });
    }
}
