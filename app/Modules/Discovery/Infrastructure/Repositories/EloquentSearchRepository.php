<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Discovery\Domain\Contracts\SearchRepository;
use App\Modules\Identity\Domain\Models\VendorProfile;

class EloquentSearchRepository implements SearchRepository
{
    public function resolveOccasionId(string $code): ?int
    {
        return Occasion::where('code', $code)->value('id');
    }

    public function resolveVendorId(string $publicId): ?int
    {
        return VendorProfile::where('public_id', $publicId)->value('id');
    }

    public function resolveCategoryId(string $publicId): ?int
    {
        return Category::where('public_id', $publicId)->value('id');
    }
}
