<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Catalog\Domain\Events\ServiceSubmittedForReview;
use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;

class OnServiceSubmittedForReviewInbox
{
    public function __construct(private readonly RouteToAdminInboxAction $router) {}

    public function handle(ServiceSubmittedForReview $event): void
    {
        $this->router->execute(
            eventKey: 'service.submitted_for_review',
            severity: AdminInboxSeverity::Info,
            sourceType: 'service',
            sourceId: $event->serviceId,
            title: ['en' => 'Service submitted for review', 'ar' => 'خدمة مُقدَّمة للمراجعة'],
            body: ['en' => "Service #{$event->serviceId} is awaiting content review.", 'ar' => "الخدمة #{$event->serviceId} في انتظار مراجعة المحتوى."],
        );
    }
}
