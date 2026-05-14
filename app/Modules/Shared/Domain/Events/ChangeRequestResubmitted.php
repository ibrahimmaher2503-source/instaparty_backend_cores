<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Events;

use App\Modules\Shared\Domain\Models\ChangeRequest;
use Illuminate\Foundation\Events\Dispatchable;

class ChangeRequestResubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly ChangeRequest $changeRequest,
        public readonly mixed $subject, // VendorProfile or Service
    ) {}
}
