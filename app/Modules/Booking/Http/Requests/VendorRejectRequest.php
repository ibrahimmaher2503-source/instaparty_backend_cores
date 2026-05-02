<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['nullable', 'array'],
            'rejection_reason.en' => ['nullable', 'string', 'max:500'],
            'rejection_reason.ar' => ['nullable', 'string', 'max:500'],
        ];
    }
}
