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
            'q' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', Rule::enum(ProductType::class)],
            'category_slug' => ['nullable', 'string'],
            'occasion' => ['nullable', 'string'],
            'occasion_slug' => ['nullable', 'string'],
            'vendor_public_id' => ['nullable', 'string'],
            'city_public_id' => ['nullable', 'string'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0', 'gte:price_min'],
            'min_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc', 'rating_desc', 'newest'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
