<?php
declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequestMessage;

final class ServiceChangeRequestClarificationRequested
{
    public function __construct(
        public readonly ServiceChangeRequest $changeRequest,
        public readonly ServiceChangeRequestMessage $message,
    ) {}
}
