<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Identity\Domain\Events\VendorRegistered;

class OnVendorRegisteredInbox
{
    public function __construct(private readonly RouteToAdminInboxAction $router) {}

    public function handle(VendorRegistered $event): void
    {
        $id = $event->vendorProfile->id;

        $this->router->execute(
            eventKey: 'vendor.registered',
            severity: AdminInboxSeverity::Info,
            sourceType: 'vendor_profile',
            sourceId: $id,
            title: ['en' => 'New vendor registration', 'ar' => 'تسجيل مورد جديد'],
            body: ['en' => "Vendor profile #{$id} is awaiting review.", 'ar' => "ملف المورد #{$id} في انتظار المراجعة."],
        );
    }
}
