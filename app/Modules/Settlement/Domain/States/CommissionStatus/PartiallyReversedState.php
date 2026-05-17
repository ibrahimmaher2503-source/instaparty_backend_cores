<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\States\CommissionStatus;

final class PartiallyReversedState extends CommissionState
{
    public static string $name = 'partially_reversed';
}
