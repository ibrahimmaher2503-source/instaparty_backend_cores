<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckServiceAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            // Required for rentals — enforced per-type in the Action, since the
            // product type is only known after the service is resolved.
            'starts_at' => ['nullable', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
