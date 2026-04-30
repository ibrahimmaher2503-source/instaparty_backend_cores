<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleServiceCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Service $service) {}
}
