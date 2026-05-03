<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum CampaignTargetLocale: string
{
    case Ar = 'ar';
    case En = 'en';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Ar => 'Arabic only',
            self::En => 'English only',
            self::Both => 'Both (EN + AR)',
        };
    }
}
