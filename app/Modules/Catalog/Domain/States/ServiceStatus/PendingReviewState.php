<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\States\ServiceStatus;

final class PendingReviewState extends ServiceState
{
    public static string $name = 'pending_review';
}
