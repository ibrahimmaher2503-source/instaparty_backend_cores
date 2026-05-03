<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Wallet = 'wallet';
    case Installment = 'installment';
    case CashOnDelivery = 'cash_on_delivery';
    case Transfer = 'transfer';
}
