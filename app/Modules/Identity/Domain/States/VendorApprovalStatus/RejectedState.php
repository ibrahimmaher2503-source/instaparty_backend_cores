<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus;

final class RejectedState extends VendorApprovalState
{
    public static string $name = 'rejected';
}
