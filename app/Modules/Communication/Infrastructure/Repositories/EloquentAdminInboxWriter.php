<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Repositories;

use App\Modules\Communication\Domain\Contracts\AdminInboxWriter;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use Illuminate\Support\Str;

final class EloquentAdminInboxWriter implements AdminInboxWriter
{
    public function create(
        string $sourceType,
        int $sourceId,
        AdminInboxSeverity $severity,
        array $title,
        array $body,
        ?int $assignedToAdminId = null,
    ): AdminInboxItem {
        return AdminInboxItem::create([
            'public_id'            => Str::ulid()->toBase32(),
            'source_type'          => $sourceType,
            'source_id'            => $sourceId,
            'severity'             => $severity,
            'title'                => $title,
            'body'                 => $body,
            'assigned_to_admin_id' => $assignedToAdminId,
        ]);
    }
}
