<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Contracts;

use App\Modules\Booking\Application\DTOs\ServiceReadDTO;

interface CatalogServiceReader
{
    public function findPublishedById(int $id): ?ServiceReadDTO;

    public function resolvePublicId(string $publicId): ?int;
}
