<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

use App\Modules\Communication\Domain\Enums\NotificationAudience;

interface NotificationDispatcher
{
    public function dispatch(
        string $eventKey,
        int $userId,
        NotificationAudience $audience,
        array $context = [],
        ?string $referenceType = null,
        ?int $referenceId = null
    ): void;
}
