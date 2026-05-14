<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum ComplianceEventType: string
{
    case ReminderSent = 'reminder_sent';
    case Expired = 'expired';
    case AutoSuspended = 'auto_suspended';
    case ManuallyOverridden = 'manually_overridden';

    public function label(): array
    {
        return match ($this) {
            self::ReminderSent => [
                'en' => 'Reminder Sent',
                'ar' => 'تم إرسال تذكير',
            ],
            self::Expired => [
                'en' => 'Document Expired',
                'ar' => 'انتهت صلاحية الوثيقة',
            ],
            self::AutoSuspended => [
                'en' => 'Automatically Suspended',
                'ar' => 'تم التعليق التلقائي',
            ],
            self::ManuallyOverridden => [
                'en' => 'Grace Period Granted',
                'ar' => 'تم منح فترة إمهال',
            ],
        };
    }
}
