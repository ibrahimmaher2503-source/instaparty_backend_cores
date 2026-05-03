<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Spatie\LaravelData\Data;

class NotificationPreferenceDTO extends Data
{
    public function __construct(
        public readonly int $userId,
        public readonly NotificationChannel $channel,
        public readonly EventCategory $eventCategory,
        public readonly bool $isEnabled,
        public readonly ?string $quietHoursStart = null,
        public readonly ?string $quietHoursEnd = null,
        public readonly ?string $timezone = null,
    ) {}
}
