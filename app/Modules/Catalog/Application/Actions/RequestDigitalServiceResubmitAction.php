<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Models\ChangeRequest;

class RequestDigitalServiceResubmitAction
{
    public function __construct(
        private ServiceResubmitAfterChangesAction $resubmitAction,
    ) {}

    public function execute(Service $service, array $changedFields, User $vendor, string $idempotencyKey): ChangeRequest
    {
        return $this->resubmitAction->execute($service, $changedFields, $vendor, $idempotencyKey);
    }
}
