<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Listeners;

use App\Modules\Catalog\Domain\Events\ServiceArchived;
use App\Modules\Catalog\Domain\Events\ServicePublished;

class ServiceIndexListener
{
    public function handlePublished(ServicePublished $event): void
    {
        $event->service->searchable();
    }

    public function handleArchived(ServiceArchived $event): void
    {
        $event->service->unsearchable();
    }
}
