<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceSubmittedForReview
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly int $serviceId) {}
}
