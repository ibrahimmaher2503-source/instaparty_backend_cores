<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus;

final class ApprovedState extends VendorApprovalState
{
    public static string $name = 'approved';
}
