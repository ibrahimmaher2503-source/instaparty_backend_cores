<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Payments\Domain\Events\PaymentFailed;

class OnPaymentFailedInbox
{
    public function __construct(private readonly RouteToAdminInboxAction $router) {}

    public function handle(PaymentFailed $event): void
    {
        $this->router->execute(
            eventKey: 'payment.failed',
            severity: AdminInboxSeverity::Critical,
            sourceType: 'payment',
            sourceId: $event->paymentId,
            title: ['en' => 'Payment failure', 'ar' => 'فشل في عملية الدفع'],
            body: ['en' => "Payment #{$event->paymentId} for booking #{$event->bookingId} failed: {$event->failureCode}.", 'ar' => "فشل الدفع #{$event->paymentId} للحجز #{$event->bookingId}: {$event->failureCode}."],
        );
    }
}
