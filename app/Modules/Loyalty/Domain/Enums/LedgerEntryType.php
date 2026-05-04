<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Enums;

enum LedgerEntryType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Reversal = 'reversal';
    case VoidRelease = 'void_release';
}
