<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum CampaignChannel: string
{
    case Push = 'push';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Push => 'Push Notification',
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
        };
    }

    public function toNotificationChannel(): NotificationChannel
    {
        return match ($this) {
            self::Push => NotificationChannel::Push,
            self::Sms => NotificationChannel::Sms,
            self::WhatsApp => NotificationChannel::Whatsapp,
            self::Email => NotificationChannel::Email,
        };
    }
}
