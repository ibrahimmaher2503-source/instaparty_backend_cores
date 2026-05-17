<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Models\AdminInboxItem;

interface AdminInboxWriter
{
    public function create(
        string $sourceType,
        int $sourceId,
        AdminInboxSeverity $severity,
        array $title,
        array $body,
        ?int $assignedToAdminId = null,
    ): AdminInboxItem;
}
