<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

enum ServiceChangeRequestStatus: string
{
    case Pending = 'pending';
    case AwaitingClarification = 'awaiting_clarification';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case CancelledVendorSuspended = 'cancelled_vendor_suspended';
    case CancelledServiceUnavailable = 'cancelled_service_unavailable';

    public function label(): string
    {
        return __('catalog.service_change_request_status_'.$this->value);
    }

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::AwaitingClarification;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Approved, self::Rejected,
            self::CancelledVendorSuspended, self::CancelledServiceUnavailable => true,
            default => false,
        };
    }
}
