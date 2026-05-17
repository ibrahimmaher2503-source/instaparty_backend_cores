<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum ChatFlagType: string
{
    case Phone = 'phone';
    case Email = 'email';
    case Profanity = 'profanity';
    case ExternalLink = 'external_link';
    case Other = 'other';
}
