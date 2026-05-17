<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum ChatMessageFlagReason: string
{
    case PhonePattern = 'phone_pattern';
    case EmailPattern = 'email_pattern';
    case ExternalLink = 'external_link';
    case Manual = 'manual';
    case PostLock = 'post_lock';
}
