<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

enum LedgerEntryType: string
{
    case CommissionCredit = 'commission_credit';
    case RefundDebit = 'refund_debit';
    case WithdrawalDebit = 'withdrawal_debit';
    case ManualAdjustment = 'manual_adjustment';
}
