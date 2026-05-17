<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

enum SuspenseAccount: int
{
    case GatewayInTransit             = 1;
    case PlatformClearing             = 2;
    case PlatformCommissionReceivable = 3;
    case PlatformCommissionRealised   = 4;
    case PlatformRefundPayable        = 5;
    case PlatformWithdrawalPayable    = 6;
    case PlatformAdjustments          = 7;

    public function label(): string
    {
        return match ($this) {
            self::GatewayInTransit             => 'Gateway In Transit',
            self::PlatformClearing             => 'Platform Clearing',
            self::PlatformCommissionReceivable => 'Platform Commission Receivable',
            self::PlatformCommissionRealised   => 'Platform Commission Realised',
            self::PlatformRefundPayable        => 'Platform Refund Payable',
            self::PlatformWithdrawalPayable    => 'Platform Withdrawal Payable',
            self::PlatformAdjustments          => 'Platform Adjustments',
        };
    }
}
