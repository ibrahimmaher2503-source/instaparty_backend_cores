<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class UpsertVendorBusinessHoursAction
{
    public function execute(VendorProfile $vendorProfile, array $hours): array
    {
        return DB::transaction(function () use ($vendorProfile, $hours): array {
            $rows = array_map(fn (array $hour): array => [
                'vendor_profile_id' => $vendorProfile->id,
                'day_of_week' => $hour['day_of_week'],
                'opens_at' => $hour['opens_at'] ?? null,
                'closes_at' => $hour['closes_at'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ], $hours);

            VendorBusinessHour::upsert(
                $rows,
                uniqueBy: ['vendor_profile_id', 'day_of_week'],
                update: ['opens_at', 'closes_at', 'updated_at'],
            );

            return VendorBusinessHour::where('vendor_profile_id', $vendorProfile->id)
                ->orderBy('day_of_week')
                ->get()
                ->toArray();
        });
    }
}
