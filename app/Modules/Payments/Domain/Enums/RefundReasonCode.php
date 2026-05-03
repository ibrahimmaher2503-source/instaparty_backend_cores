<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Enums;

enum RefundReasonCode: string
{
    case CustomerRequest = 'customer_request';
    case VendorCancellation = 'vendor_cancellation';
    case ServiceUnavailable = 'service_unavailable';
    case DuplicateCharge = 'duplicate_charge';
    case AdminDiscretion = 'admin_discretion';

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
