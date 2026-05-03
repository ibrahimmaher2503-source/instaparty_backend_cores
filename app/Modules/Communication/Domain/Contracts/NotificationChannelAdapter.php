<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

use App\Modules\Communication\Domain\Models\NotificationDispatch;

interface NotificationChannelAdapter
{
    public function send(NotificationDispatch $dispatch): void;
}
