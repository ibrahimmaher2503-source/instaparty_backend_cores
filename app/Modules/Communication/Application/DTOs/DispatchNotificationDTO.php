<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Spatie\LaravelData\Data;

class DispatchNotificationDTO extends Data
{
    public function __construct(
        public readonly string $eventKey,
        public readonly NotificationChannel $channel,
        public readonly NotificationAudience $audience,
        public readonly EventCategory $eventCategory,
        public readonly int $userId,
        public readonly array $context = [],
        public readonly ?string $referenceType = null,
        public readonly ?int $referenceId = null,
        /** When set, bypasses TemplateResolver — used by campaign dispatch. */
        public readonly ?string $directBody = null,
        public readonly ?string $directSubject = null,
    ) {}
}
