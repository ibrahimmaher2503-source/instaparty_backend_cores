<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Application\DTOs\ServiceThemeDTO;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateServiceThemeAction
{
    use RecordsTaxonomyAudit;

    public function execute(ServiceThemeDTO $dto, ?User $actor = null): ServiceTheme
    {
        return DB::transaction(function () use ($dto, $actor): ServiceTheme {
            $theme = ServiceTheme::create([
                'public_id' => (string) Str::ulid(),
                ...$dto->toAttributes(),
            ]);

            $this->recordAudit($theme, 'service_theme_created', $actor, $dto->toAttributes());

            return $theme;
        });
    }
}
