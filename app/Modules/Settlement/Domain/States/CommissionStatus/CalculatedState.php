<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\States\CommissionStatus;

final class CalculatedState extends CommissionState
{
    public static string $name = 'calculated';
}
