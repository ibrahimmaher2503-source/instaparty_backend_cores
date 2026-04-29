<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;

class RentalServiceResource extends ServiceBaseResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $detail = $this->rentalDetail;

        return array_merge($this->baseFields(), [
            'rental' => [
                'requires_electricity' => $detail?->requires_electricity,
                'requires_outdoor_space' => $detail?->requires_outdoor_space,
                'default_rental_duration_hours' => $detail?->default_rental_duration_hours,
                'setup_time_minutes' => $detail?->setup_time_minutes,
                'teardown_time_minutes' => $detail?->teardown_time_minutes,
                'security_deposit' => $detail !== null ? [
                    'minor' => $detail->security_deposit_minor,
                    'currency' => $detail->security_deposit_currency,
                    'display' => number_format($detail->security_deposit_minor / 100, 2).' '.($detail->security_deposit_currency ?? 'EGP'),
                ] : null,
                'minimum_space_sqm' => $detail?->minimum_space_sqm,
            ],
        ]);
    }
}
