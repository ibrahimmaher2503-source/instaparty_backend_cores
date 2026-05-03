<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Application\DTOs\NotificationPreferenceDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Communication\Infrastructure\Repositories\EloquentNotificationPreferenceRepository;
use RuntimeException;

class SystemCategoryCannotBeDisabledException extends RuntimeException
{
    public string $errorCode = 'system_notifications_cannot_be_disabled';
}

class UpdateNotificationPreferenceAction
{
    public function __construct(
        private readonly EloquentNotificationPreferenceRepository $repository,
    ) {}

    public function execute(NotificationPreferenceDTO $dto): NotificationPreference
    {
        if ($dto->eventCategory === EventCategory::System && ! $dto->isEnabled) {
            throw new SystemCategoryCannotBeDisabledException(
                'System notifications cannot be disabled.'
            );
        }

        return $this->repository->upsert($dto);
    }
}
