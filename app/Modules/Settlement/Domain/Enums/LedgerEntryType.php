<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

enum LedgerEntryType: string
{
    // Legacy (existing rows retain these values)
    case CommissionCredit = 'commission_credit';
    case RefundDebit      = 'refund_debit';
    case WithdrawalDebit  = 'withdrawal_debit';
    case ManualAdjustment = 'manual_adjustment';

    // Phase 4.9 additions
    case PaymentCapture          = 'payment_capture';
    case RefundCreditCustomer    = 'refund_credit_customer';
    case RefundDebitPlatform     = 'refund_debit_platform';
    case CommissionAccrual       = 'commission_accrual';
    case CommissionReversal      = 'commission_reversal';
    case WithdrawalReserve       = 'withdrawal_reserve';
    case WithdrawalSettle        = 'withdrawal_settle';
    case WithdrawalRejectRelease = 'withdrawal_reject_release';
    case ManualAdjustmentDebit   = 'manual_adjustment_debit';
    case ManualAdjustmentCredit  = 'manual_adjustment_credit';
    case SuspenseMovement        = 'suspense_movement';
    case LegacyBackfill          = 'legacy_backfill';
    case VendorCredit            = 'vendor_credit';
}
