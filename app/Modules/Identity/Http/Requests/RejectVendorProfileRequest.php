<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectVendorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['nullable', 'array'],
            'rejection_reason.en' => ['required_with:rejection_reason', 'string', 'max:1000'],
            'rejection_reason.ar' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
