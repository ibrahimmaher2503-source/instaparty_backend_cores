<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Listeners;

use App\Modules\Identity\Domain\Events\VendorApproved;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Subscriptions\Application\Actions\AutoEnrolFreeTierAction;

class OnVendorRegistered
{
    public function __construct(private readonly AutoEnrolFreeTierAction $action) {}

    public function handle(VendorRegistered|VendorApproved $event): void
    {
        $this->action->execute($event->vendorProfile->id);
    }
}
