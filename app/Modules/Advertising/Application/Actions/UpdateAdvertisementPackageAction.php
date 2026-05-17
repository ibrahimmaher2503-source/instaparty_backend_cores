<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Application\Actions;

use App\Modules\Advertising\Domain\Models\AdvertisementPackage;
use Illuminate\Support\Facades\DB;

class UpdateAdvertisementPackageAction
{
    public function execute(AdvertisementPackage $package, array $data): AdvertisementPackage
    {
        return DB::transaction(function () use ($package, $data): AdvertisementPackage {
            $package->update($data);
            return $package->fresh();
        });
    }
}
