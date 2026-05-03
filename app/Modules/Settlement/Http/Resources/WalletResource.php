<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     public_id: string|null,
 *     currency: string,
 *     balance_minor: int,
 *     pending_withdrawal_minor: int,
 *     available_minor: int,
 *     is_negative: bool,
 *     totals: array{credits_minor: int, debits_minor: int}
 * } $resource
 */
class WalletResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $walletData
     */
    public static function fromArray(array $walletData): self
    {
        return new self($walletData);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->resource['currency'];

        return [
            'public_id' => $this->resource['public_id'],
            'currency' => $currency,
            'balance_minor' => $this->resource['balance_minor'],
            'balance_formatted' => $this->formatMoney($this->resource['balance_minor'], $currency),
            'pending_withdrawal_minor' => $this->resource['pending_withdrawal_minor'],
            'pending_withdrawal_formatted' => $this->formatMoney($this->resource['pending_withdrawal_minor'], $currency),
            'available_minor' => $this->resource['available_minor'],
            'available_formatted' => $this->formatMoney($this->resource['available_minor'], $currency),
            'is_negative' => $this->resource['is_negative'],
            'totals' => [
                'credits_minor' => $this->resource['totals']['credits_minor'],
                'credits_formatted' => $this->formatMoney($this->resource['totals']['credits_minor'], $currency),
                'debits_minor' => $this->resource['totals']['debits_minor'],
                'debits_formatted' => $this->formatMoney($this->resource['totals']['debits_minor'], $currency),
            ],
        ];
    }

    private function formatMoney(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2).' '.$currency;
    }
}
