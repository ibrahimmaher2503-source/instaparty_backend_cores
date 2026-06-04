<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::in(['ios', 'android', 'web'])],
            'fcm_token' => ['required', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:190'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'platform.required' => __('identity::identity.validation.platform_required'),
            'platform.in' => __('identity::identity.validation.platform_invalid'),
            'fcm_token.required' => __('identity::identity.validation.fcm_token_required'),
        ];
    }
}
