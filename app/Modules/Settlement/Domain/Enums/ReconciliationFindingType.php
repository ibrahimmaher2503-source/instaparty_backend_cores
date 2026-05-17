<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

enum ReconciliationFindingType: string
{
    case WalletCacheDrift                = 'wallet_cache_drift';
    case OrphanedRefundRow               = 'orphaned_refund_row';
    case OrphanedLedgerEntry             = 'orphaned_ledger_entry';
    case UnbalancedTransactionGroup      = 'unbalanced_transaction_group';
    case CommissionWithoutSnapshotRate   = 'commission_without_snapshot_rate';
    case WithdrawalWithoutReserveEntry   = 'withdrawal_without_reserve_entry';
    case NegativeVendorBalance           = 'negative_vendor_balance';
    case CurrencyMismatch                = 'currency_mismatch';
}
