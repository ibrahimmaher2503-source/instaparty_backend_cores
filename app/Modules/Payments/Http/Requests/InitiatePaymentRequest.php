<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * @bodyParam method string required Payment method. Example: card
     */
    public function rules(): array
    {
        return ['method' => ['required', 'string', Rule::in(array_map(static fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases()))]];
    }
}
