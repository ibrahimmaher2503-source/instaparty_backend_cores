<?php
declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;

final class ServiceChangeRequestRejected
{
    public function __construct(
        public readonly ServiceChangeRequest $changeRequest,
    ) {}
}
