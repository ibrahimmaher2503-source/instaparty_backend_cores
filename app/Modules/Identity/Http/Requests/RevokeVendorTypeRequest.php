<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RevokeVendorTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type' => ['required', new Enum(ProductType::class)],
            'revoke_reason' => ['nullable', 'array'],
            'revoke_reason.en' => ['nullable', 'string', 'max:1000'],
            'revoke_reason.ar' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
