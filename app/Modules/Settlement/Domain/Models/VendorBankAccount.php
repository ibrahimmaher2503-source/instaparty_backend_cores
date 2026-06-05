<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VendorBankAccount extends Model
{
    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'bank_name',
        'account_holder',
        'iban',
        'swift_bic',
        'branch',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /** Masked IBAN for API exposure: first 4 + last 4 only. */
    public function maskedIban(): string
    {
        $iban = (string) $this->iban;

        return strlen($iban) <= 8
            ? $iban
            : substr($iban, 0, 4).str_repeat('*', max(0, strlen($iban) - 8)).substr($iban, -4);
    }
}
