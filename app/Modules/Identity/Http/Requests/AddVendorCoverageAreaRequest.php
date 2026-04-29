<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddVendorCoverageAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('vendor') ?? false;
    }

    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'delivery_fee_minor' => ['nullable', 'integer', 'min:0'],
            'delivery_fee_currency' => ['nullable', 'string', 'size:3'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'],
            'min_order_currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
