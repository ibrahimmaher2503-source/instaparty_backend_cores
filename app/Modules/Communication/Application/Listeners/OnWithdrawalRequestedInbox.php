<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Settlement\Domain\Events\WithdrawalRequested;

class OnWithdrawalRequestedInbox
{
    public function __construct(private readonly RouteToAdminInboxAction $router) {}

    public function handle(WithdrawalRequested $event): void
    {
        $this->router->execute(
            eventKey: 'withdrawal.requested',
            severity: AdminInboxSeverity::Warning,
            sourceType: 'withdrawal',
            sourceId: $event->withdrawalId,
            title: ['en' => 'Withdrawal request', 'ar' => 'طلب سحب رصيد'],
            body: ['en' => "Vendor #{$event->vendorProfileId} requested withdrawal #{$event->withdrawalPublicId}.", 'ar' => "المورد #{$event->vendorProfileId} طلب سحب #{$event->withdrawalPublicId}."],
        );
    }
}
