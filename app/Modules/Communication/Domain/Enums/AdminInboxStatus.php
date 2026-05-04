<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum AdminInboxStatus: string
{
    case Unread = 'unread';
    case Read = 'read';
    case Snoozed = 'snoozed';
    case Resolved = 'resolved';
    case Reassigned = 'reassigned';

    public function label(): string
    {
        return match ($this) {
            self::Unread => 'Unread',
            self::Read => 'Read',
            self::Snoozed => 'Snoozed',
            self::Resolved => 'Resolved',
            self::Reassigned => 'Reassigned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unread => 'warning',
            self::Read => 'info',
            self::Snoozed => 'gray',
            self::Resolved => 'success',
            self::Reassigned => 'primary',
        };
    }

    /** @return array<string, string> */
    public static function activeStatuses(): array
    {
        return [self::Unread->value, self::Read->value];
    }
}
