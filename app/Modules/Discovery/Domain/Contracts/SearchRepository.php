<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Domain\Contracts;

interface SearchRepository
{
    public function resolveOccasionId(string $code): ?int;

    public function resolveVendorId(string $publicId): ?int;

    public function resolveCategoryId(string $publicId): ?int;
}
