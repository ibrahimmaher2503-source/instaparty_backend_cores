<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam name.en string required Program name in English. Example: Birthday Points
 * @bodyParam name.ar string required Program name in Arabic. Example: نقاط أعياد الميلاد
 * @bodyParam terms.en string nullable Terms and conditions in English.
 * @bodyParam terms.ar string nullable Terms and conditions in Arabic.
 * @bodyParam expiration_days integer nullable Days before points expire (null = never). Example: 365
 * @bodyParam currency string nullable Currency code. Default: EGP. Example: EGP
 */
class StoreLoyaltyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:150'],
            'name.ar' => ['required', 'string', 'max:150'],
            'terms' => ['nullable', 'array'],
            'terms.en' => ['nullable', 'string', 'max:2000'],
            'terms.ar' => ['nullable', 'string', 'max:2000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expiration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
