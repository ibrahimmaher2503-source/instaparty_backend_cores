<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteOccasionAction
{
    use RecordsTaxonomyAudit;

    public function execute(Occasion $occasion, ?User $actor = null): void
    {
        DB::transaction(function () use ($occasion, $actor): void {
            $this->recordAudit($occasion, 'occasion_deleted', $actor, ['code' => $occasion->code]);
            $occasion->delete();
        });
    }
}
