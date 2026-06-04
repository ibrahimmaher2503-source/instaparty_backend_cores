<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorUploadConditionPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->vendorProfile !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phase' => ['required', Rule::in(['handover', 'return'])],
            'photos' => ['required', 'array', 'min:1', 'max:6'],
            'photos.*' => ['image', 'max:10240'],
        ];
    }
}
