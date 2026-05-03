<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @bodyParam method string required Payment method. Phase 1 only accepts `card`. Example: card
     */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['card'])],
        ];
    }
}
