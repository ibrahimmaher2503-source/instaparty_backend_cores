<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Resources;

use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WalletLedgerEntry
 */
class WalletLedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en');
        $locale = in_array($locale, ['en', 'ar'], true) ? $locale : 'en';

        return [
            'entry_type' => $this->entry_type->value,
            'amount_minor' => $this->amount_minor,
            'amount_formatted' => $this->formatAmount(),
            'currency' => $this->currency,
            'description' => $this->resolveDescription($locale),
            'related' => $this->related_entity_type !== null
                ? ['type' => $this->related_entity_type, 'public_id' => null]
                : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function resolveDescription(string $locale): string
    {
        if ($this->description_key === null) {
            return $this->entry_type->value;
        }

        $params = $this->description_params ?? [];
        $translated = __($this->description_key, $params, $locale);

        // __() returns the key itself when not found — fall back gracefully
        return ($translated !== $this->description_key) ? (string) $translated : $this->entry_type->value;
    }

    private function formatAmount(): string
    {
        $sign = $this->amount_minor < 0 ? '-' : '';

        return $sign.number_format(abs($this->amount_minor) / 100, 2).' '.$this->currency;
    }
}
