<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus;

final class PendingState extends VendorApprovalState
{
    public static string $name = 'pending';
}
