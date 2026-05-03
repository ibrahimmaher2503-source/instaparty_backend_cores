<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @bodyParam name.en string Program name in English. Example: Birthday Points
 * @bodyParam name.ar string Program name in Arabic. Example: نقاط أعياد الميلاد
 * @bodyParam terms.en string nullable Terms in English.
 * @bodyParam terms.ar string nullable Terms in Arabic.
 * @bodyParam status string Program status. Enum: active,paused,archived. Example: paused
 * @bodyParam expiration_days integer nullable Days before points expire. Example: 365
 */
class UpdateLoyaltyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['sometimes', 'array'],
            'name.en'         => ['sometimes', 'string', 'max:150'],
            'name.ar'         => ['sometimes', 'string', 'max:150'],
            'terms'           => ['nullable', 'array'],
            'terms.en'        => ['nullable', 'string', 'max:2000'],
            'terms.ar'        => ['nullable', 'string', 'max:2000'],
            'status'          => ['sometimes', Rule::in(['active', 'paused', 'archived'])],
            'expiration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
