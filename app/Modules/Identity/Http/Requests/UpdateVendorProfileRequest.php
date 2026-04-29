<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Domain\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateVendorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['sometimes', 'array'],
            'business_name.en' => ['sometimes', 'string', 'max:255'],
            'business_name.ar' => ['sometimes', 'string', 'max:255'],
            'bio' => ['sometimes', 'array'],
            'bio.en' => ['sometimes', 'string', 'max:2000'],
            'bio.ar' => ['sometimes', 'string', 'max:2000'],
            'business_type' => ['sometimes', new Enum(BusinessType::class)],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bank_account_holder' => ['sometimes', 'nullable', 'string', 'max:160'],
            'bank_iban' => ['sometimes', 'nullable', 'string', 'max:34'],
            'bank_swift_bic' => ['sometimes', 'nullable', 'string', 'max:11'],
            'bank_branch' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferred_locale' => ['sometimes', 'in:en,ar'],
        ];
    }
}
