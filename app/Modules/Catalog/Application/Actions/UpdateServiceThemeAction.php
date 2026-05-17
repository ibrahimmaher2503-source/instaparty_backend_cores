<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Application\DTOs\ServiceThemeDTO;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateServiceThemeAction
{
    use RecordsTaxonomyAudit;

    public function execute(ServiceTheme $theme, ServiceThemeDTO $dto, ?User $actor = null): ServiceTheme
    {
        return DB::transaction(function () use ($theme, $dto, $actor): ServiceTheme {
            $theme->update($dto->toAttributes());

            $this->recordAudit($theme, 'service_theme_updated', $actor, $dto->toAttributes());

            return $theme->refresh();
        });
    }
}
