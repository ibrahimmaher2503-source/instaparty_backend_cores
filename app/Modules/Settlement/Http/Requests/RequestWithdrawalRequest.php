<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Requests;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RequestWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settlement.request_withdrawal.own') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'bank_account' => ['required', 'array'],
            'bank_account.account_holder' => ['required', 'string', 'max:255'],
            'bank_account.iban' => ['required', 'string', 'min:4', 'max:34'],
            'bank_account.bank_name' => ['required', 'string', 'max:255'],
            'bank_account.swift_bic' => ['required', 'string', 'max:11'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $iban = (string) $this->input('bank_account.iban', '');
            if ($iban !== '' && ! BankAccountSnapshot::validateIban($iban)) {
                $v->errors()->add('bank_account.iban', __('settlement::settlement.errors.invalid_iban'));
            }
        });
    }

    public function toDto(): RequestWithdrawalDto
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new NotFoundHttpException('Authenticated user not found.');
        }

        $vendorProfile = $user->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return new RequestWithdrawalDto(
            vendorProfileId: $vendorProfile->id,
            requestedByUserId: $user->id,
            amountMinor: $this->integer('amount_minor'),
            currency: strtoupper((string) $this->input('currency')),
            bankAccount: BankAccountSnapshot::fromArray((array) $this->input('bank_account')),
        );
    }
}
