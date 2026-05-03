<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Contracts;

use App\Modules\Settlement\Application\DTOs\PaymentSnapshotDto;
use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;

interface SettlementPaymentReader
{
    public function findById(int $id): ?PaymentSnapshotDto;

    public function findRefundById(int $id): ?RefundSnapshotDto;
}
