<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Policies;

use App\Modules\Catalog\Domain\Models\Service;

class ChangeRequestPolicy
{
    public const int MAX_CYCLES = 3;

    public function canRequestChanges(Service $service): bool
    {
        return true;
    }
}
