<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

use RuntimeException;

final class OverRefundAttemptedException extends RuntimeException
{
    public function __construct(int $paymentId, int $capturedTotal, int $alreadyRefunded, int $requested)
    {
        $remaining = $capturedTotal - $alreadyRefunded;
        parent::__construct(
            "Payment {$paymentId}: refund of {$requested} piastres would exceed the remaining refundable amount of {$remaining} piastres (captured={$capturedTotal}, already refunded={$alreadyRefunded})."
        );
    }
}
