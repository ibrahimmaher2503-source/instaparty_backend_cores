<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteServiceThemeAction
{
    use RecordsTaxonomyAudit;

    public function execute(ServiceTheme $theme, ?User $actor = null): void
    {
        DB::transaction(function () use ($theme, $actor): void {
            $this->recordAudit($theme, 'service_theme_deleted', $actor, ['code' => $theme->code]);
            $theme->delete();
        });
    }
}
