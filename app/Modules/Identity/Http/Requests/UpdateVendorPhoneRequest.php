<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @bodyParam phone_e164 string required Phone number in E.164 format. Example: "+201012345678"
 */
class UpdateVendorPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone_e164' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique('users', 'phone_e164')],
        ];
    }
}
