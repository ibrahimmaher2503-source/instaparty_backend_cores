<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus;

final class ChangesRequestedState extends VendorApprovalState
{
    public static string $name = 'changes_requested';
}
