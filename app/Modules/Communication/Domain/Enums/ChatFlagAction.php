<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum ChatFlagAction: string
{
    case Redact = 'redact';
    case Warn = 'warn';
    case Block = 'block';
    case None = 'none';
}
