<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\States\PaymentStatus;

final class CapturedState extends PaymentState
{
    public static string $name = 'captured';
}
