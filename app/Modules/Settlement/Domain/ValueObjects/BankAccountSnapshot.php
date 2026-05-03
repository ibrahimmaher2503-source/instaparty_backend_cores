<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class BankAccountSnapshot
{
    public function __construct(
        public string $account_holder,
        public string $iban,
        public string $bank_name,
        public string $swift_bic,
    ) {
        if (! self::validateIban($this->iban)) {
            throw new InvalidArgumentException(
                "Invalid IBAN provided: {$this->iban}"
            );
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            account_holder: (string) ($data['account_holder'] ?? ''),
            iban: (string) ($data['iban'] ?? ''),
            bank_name: (string) ($data['bank_name'] ?? ''),
            swift_bic: (string) ($data['swift_bic'] ?? ''),
        );
    }

    /** @return array{account_holder: string, iban: string, bank_name: string, swift_bic: string} */
    public function toArray(): array
    {
        return [
            'account_holder' => $this->account_holder,
            'iban' => $this->iban,
            'bank_name' => $this->bank_name,
            'swift_bic' => $this->swift_bic,
        ];
    }

    /**
     * Validate an IBAN using the ISO-13616 mod-97 algorithm.
     */
    public static function validateIban(string $iban): bool
    {
        // Remove spaces and convert to uppercase
        $iban = strtoupper(str_replace(' ', '', $iban));

        // Basic length check (2-char country code + 2-char check digits + up to 30 chars)
        if (strlen($iban) < 4 || strlen($iban) > 34) {
            return false;
        }

        // Move the first 4 characters to the end
        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        // Replace letters with digits: A=10, B=11, ..., Z=35
        $numericString = '';
        foreach (str_split($rearranged) as $char) {
            if (ctype_alpha($char)) {
                $numericString .= (string) (ord($char) - ord('A') + 10);
            } else {
                $numericString .= $char;
            }
        }

        // Compute mod-97 using chunk processing (to handle large numbers)
        $remainder = 0;
        foreach (str_split($numericString, 9) as $chunk) {
            $combined = (int) ($remainder.$chunk);
            $remainder = $combined % 97;
        }

        return $remainder === 1;
    }

    /**
     * Return the last N characters of the IBAN (useful for display/logs).
     */
    public function lastChars(int $count = 4): string
    {
        return substr($this->iban, -$count);
    }
}
