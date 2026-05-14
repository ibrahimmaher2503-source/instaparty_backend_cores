<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'points_redeemed' => (int) $this->points_redeemed,
            'amount' => [
                'minor' => (int) $this->amount_minor,
                'currency' => (string) $this->amount_currency,
            ],
            'booking_id' => (int) $this->booking_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
