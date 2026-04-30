<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Requests;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'         => ['nullable', 'string', 'max:255'],
            'type'      => ['nullable', Rule::enum(ProductType::class)],
            'category'  => ['nullable', 'string'],
            'occasion'  => ['nullable', 'string'],
            'vendor'    => ['nullable', 'string'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'sort'      => ['nullable', Rule::in(['price_asc', 'price_desc', 'rating', 'newest'])],
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
