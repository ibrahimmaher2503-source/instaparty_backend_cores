<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Events;

final readonly class WithdrawalApproved
{
    public function __construct(
        public int $withdrawalId,
        public string $withdrawalPublicId,
        public int $vendorProfileId,
        public int $approvedByAdminId,
    ) {}
}
