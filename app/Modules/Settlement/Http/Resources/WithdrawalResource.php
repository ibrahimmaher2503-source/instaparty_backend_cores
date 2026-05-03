<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Resources;

use App\Modules\Settlement\Domain\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Withdrawal
 */
class WithdrawalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en');
        $locale = in_array($locale, ['en', 'ar'], true) ? $locale : 'en';

        return [
            'public_id' => $this->public_id,
            'status' => $this->status->value,
            'requested_amount_minor' => $this->requested_amount_minor,
            'requested_amount_formatted' => $this->formatMoney($this->requested_amount_minor, $this->requested_amount_currency),
            'requested_amount_currency' => $this->requested_amount_currency,
            'paid_amount_minor' => $this->paid_amount_minor,
            'paid_amount_formatted' => $this->paid_amount_minor !== null
                ? $this->formatMoney($this->paid_amount_minor, $this->paid_amount_currency ?? $this->requested_amount_currency)
                : null,
            'bank_account' => $this->bank_account_snapshot !== null
                ? $this->maskBankAccount($this->bank_account_snapshot->iban, $this->bank_account_snapshot->account_holder, $this->bank_account_snapshot->bank_name)
                : null,
            'rejected_reason' => $this->rejected_reason !== null
                ? ($this->rejected_reason[$locale] ?? ($this->rejected_reason['en'] ?? null))
                : null,
            'requested_at' => $this->requested_at?->toISOString(),
            'processed_at' => $this->processed_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
        ];
    }

    private function formatMoney(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2).' '.$currency;
    }

    /**
     * Mask IBAN: show first 4 chars and last 3 chars; replace the rest with *.
     */
    /** @return array{account_holder: string, iban_masked: string, bank_name: string} */
    private function maskBankAccount(string $iban, string $accountHolder, string $bankName): array
    {
        $iban = str_replace(' ', '', $iban);
        $ibanLength = strlen($iban);
        $maskedIban = $ibanLength > 7
            ? substr($iban, 0, 4).str_repeat('*', $ibanLength - 7).substr($iban, -3)
            : $iban;

        return [
            'account_holder' => $accountHolder,
            'iban_masked' => $maskedIban,
            'bank_name' => $bankName,
        ];
    }
}
