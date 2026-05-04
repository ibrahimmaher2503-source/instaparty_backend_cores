<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatFlagged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly int $chatThreadId) {}
}
