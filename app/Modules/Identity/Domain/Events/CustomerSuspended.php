<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\User;

final class CustomerSuspended
{
    public function __construct(
        public readonly User $customer,
        public readonly int $actorId,
    ) {}
}
