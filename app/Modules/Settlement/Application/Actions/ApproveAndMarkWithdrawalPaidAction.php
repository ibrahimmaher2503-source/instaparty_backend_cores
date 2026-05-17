<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Application\DTOs\MarkWithdrawalPaidInput;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use Illuminate\Http\UploadedFile;

/**
 * @deprecated since 4.11 — use ApproveWithdrawalAction + MarkWithdrawalPaidAction
 * @todo Phase 8: Remove this wrapper once no internal consumer remains.
 *   Search the repo for `ApproveAndMarkWithdrawalPaidAction::class` before deleting.
 */
class ApproveAndMarkWithdrawalPaidAction
{
    /** @deprecated since 4.11 — delegates to ApproveWithdrawalAction + MarkWithdrawalPaidAction */
    public function execute(Withdrawal $withdrawal, UploadedFile $proof, User $admin): Withdrawal
    {
        $withdrawal = app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);

        return app(MarkWithdrawalPaidAction::class)->execute(
            $withdrawal,
            new MarkWithdrawalPaidInput(
                bankTransferReference: "LEGACY-{$withdrawal->public_id}",
                proofFile: $proof,
                paymentNote: [
                    'en' => 'Legacy combined action — see ADR-0032',
                    'ar' => 'إجراء قديم مُجمَّع — راجع ADR-0032',
                ],
            ),
            $admin,
        );
    }
}
