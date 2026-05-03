<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam name.en string optional (partial update) Service name in English. Example: "Custom Birthday Cake"
 * @bodyParam name.ar string optional (partial update) Service name in Arabic. Example: "كيكة عيد ميلاد"
 * @bodyParam short_description.en string optional (partial update) Short description in English.
 * @bodyParam short_description.ar string optional (partial update) Short description in Arabic.
 * @bodyParam category_id integer optional (partial update) ID of the category. Example: 2
 * @bodyParam base_price_minor integer optional (partial update) Price in EGP piastres (100 = 1 EGP). Example: 80000
 * @bodyParam is_perishable boolean optional (partial update) Whether the product is perishable. Example: true
 * @bodyParam is_made_to_order boolean optional (partial update) Whether the product is made to order. Example: true
 * @bodyParam lead_time_hours integer optional (partial update) required if is_made_to_order is true. Lead time in hours. Example: 48
 * @bodyParam stock_quantity integer optional (partial update) Quantity in stock (null means unlimited). Example: 10
 * @bodyParam customization_fields array optional (partial update) Array of customization field definitions.
 * @bodyParam customization_fields.* array Each customization field object.
 */
class UpdateSaleServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'array'],
            'name.en' => ['sometimes', 'required', 'string', 'max:255'],
            'name.ar' => ['sometimes', 'required', 'string', 'max:255'],
            'short_description' => ['sometimes', 'required', 'array'],
            'short_description.en' => ['sometimes', 'required', 'string', 'max:500'],
            'short_description.ar' => ['sometimes', 'required', 'string', 'max:500'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'base_price_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_perishable' => ['sometimes', 'boolean'],
            'is_made_to_order' => ['sometimes', 'boolean'],
            'lead_time_hours' => ['sometimes', 'required_if:is_made_to_order,true', 'integer', 'min:1', 'max:720'],
            'stock_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'customization_fields' => ['sometimes', 'nullable', 'array'],
            'customization_fields.*' => ['array'],
        ];
    }
}
