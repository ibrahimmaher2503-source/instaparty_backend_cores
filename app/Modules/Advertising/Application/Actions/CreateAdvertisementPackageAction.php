<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Application\Actions;

use App\Modules\Advertising\Domain\Models\AdvertisementPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAdvertisementPackageAction
{
    public function execute(array $data): AdvertisementPackage
    {
        return DB::transaction(fn () => AdvertisementPackage::create(
            array_merge($data, ['public_id' => Str::ulid()])
        ));
    }
}
