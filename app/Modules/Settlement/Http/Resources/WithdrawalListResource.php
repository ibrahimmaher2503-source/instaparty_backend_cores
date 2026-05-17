<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Resources;

use App\Modules\Settlement\Domain\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Withdrawal */
class WithdrawalListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id'               => $this->public_id,
            'status'                  => $this->getRawOriginal('status'),
            'amount'                  => [
                'minor'    => $this->requested_amount_minor,
                'currency' => $this->requested_amount_currency,
                'formatted'=> number_format($this->requested_amount_minor / 100, 2) . ' ' . $this->requested_amount_currency,
            ],
            'requested_at'            => $this->requested_at?->toISOString(),
            'approved_at'             => $this->approved_at?->toISOString(),
            'paid_at'                 => $this->paid_at?->toISOString(),
            'bank_transfer_reference' => $this->bank_transfer_reference,
            'has_proof'               => $this->getFirstMedia('bank_proof') !== null,
        ];
    }
}
