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

        $proofMedia = $this->getFirstMedia('bank_proof');
        $proofUrl   = null;
        $proofExpiry = null;

        if ($proofMedia !== null) {
            $expiry = now()->addMinutes(15);
            try {
                $proofUrl    = $proofMedia->getTemporaryUrl($expiry);
                $proofExpiry = $expiry->toISOString();
            } catch (\Exception) {
                $proofUrl    = null;
                $proofExpiry = null;
            }
        }

        return [
            'public_id' => $this->public_id,
            'status'    => $this->getRawOriginal('status'),
            'amount'    => [
                'minor'     => $this->requested_amount_minor,
                'currency'  => $this->requested_amount_currency,
                'formatted' => $this->formatMoney($this->requested_amount_minor, $this->requested_amount_currency),
            ],
            'paid_amount' => $this->paid_amount_minor !== null ? [
                'minor'     => $this->paid_amount_minor,
                'currency'  => $this->paid_amount_currency ?? $this->requested_amount_currency,
                'formatted' => $this->formatMoney($this->paid_amount_minor, $this->paid_amount_currency ?? $this->requested_amount_currency),
            ] : null,
            'bank_account' => $this->bank_account_snapshot !== null
                ? $this->maskBankAccount(
                    $this->bank_account_snapshot->iban,
                    $this->bank_account_snapshot->account_holder,
                    $this->bank_account_snapshot->bank_name
                )
                : null,
            'rejected_reason' => $this->rejected_reason !== null
                ? ($this->rejected_reason[$locale] ?? ($this->rejected_reason['en'] ?? null))
                : null,
            // Phase 4.11 — Finance Audit timeline
            'timeline' => [
                'requested_at' => $this->requested_at?->toISOString(),
                'approved_at'  => $this->approved_at?->toISOString(),
                'paid_at'      => $this->paid_at?->toISOString(),
            ],
            'approved_at'            => $this->approved_at?->toISOString(),
            'paid_at'                => $this->paid_at?->toISOString(),
            'requested_at'           => $this->requested_at?->toISOString(),
            'bank_transfer_reference' => $this->bank_transfer_reference,
            'admin_payment_note'     => $this->admin_payment_note !== null
                ? ($this->admin_payment_note[$locale] ?? ($this->admin_payment_note['en'] ?? null))
                : null,
            'has_proof'                   => $proofMedia !== null,
            'proof_download_url'          => $proofUrl,
            'proof_download_url_expires_at' => $proofExpiry,
            // Legacy fields kept for backward compat
            'processed_at'     => $this->processed_at?->toISOString(),
        ];
    }

    private function formatMoney(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2).' '.$currency;
    }

    /**
     * Mask IBAN: show first 4 chars and last 3 chars; replace the rest with *.
     *
     * @return array{account_holder: string, iban_masked: string, bank_name: string}
     */
    private function maskBankAccount(string $iban, string $accountHolder, string $bankName): array
    {
        $iban      = str_replace(' ', '', $iban);
        $ibanLen   = strlen($iban);
        $maskedIban = $ibanLen > 7
            ? substr($iban, 0, 4).str_repeat('*', $ibanLen - 7).substr($iban, -3)
            : $iban;

        return [
            'account_holder' => $accountHolder,
            'iban_masked'    => $maskedIban,
            'bank_name'      => $bankName,
        ];
    }
}
