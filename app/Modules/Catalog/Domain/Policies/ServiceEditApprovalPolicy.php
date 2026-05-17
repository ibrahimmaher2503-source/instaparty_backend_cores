<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Policies;

final class ServiceEditApprovalPolicy
{
    public const int MAX_CLARIFICATIONS = 3;
}
