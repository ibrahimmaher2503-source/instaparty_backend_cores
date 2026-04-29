<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_e164' => $user->phone_e164,
            'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
            'preferred_locale' => $user->preferred_locale,
            'customer_profile' => $this->whenLoaded('customerProfile', fn () => [
                'date_of_birth' => $user->customerProfile?->date_of_birth?->toDateString(),
                'gender' => $user->customerProfile?->gender,
                'accepts_marketing' => $user->customerProfile?->accepts_marketing,
            ]),
        ];
    }
}
