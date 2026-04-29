<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class CustomerRegistered
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
