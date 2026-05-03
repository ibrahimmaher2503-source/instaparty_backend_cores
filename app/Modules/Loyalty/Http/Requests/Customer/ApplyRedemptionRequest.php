<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam points integer required Number of points to redeem. Example: 400
 */
class ApplyRedemptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'min:1'],
        ];
    }
}
