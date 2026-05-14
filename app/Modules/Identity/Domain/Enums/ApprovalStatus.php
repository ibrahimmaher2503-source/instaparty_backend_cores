<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case ChangesRequested = 'changes_requested';

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isActionable(): bool
    {
        return $this === self::Pending || $this === self::ChangesRequested;
    }
}
