<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\States\ServiceStatus;

final class DraftState extends ServiceState
{
    public static string $name = 'draft';
}
