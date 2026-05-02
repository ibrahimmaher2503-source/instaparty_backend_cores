<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam name.en string required Service name in English. Example: "Custom Birthday Cake"
 * @bodyParam name.ar string required Service name in Arabic. Example: "كيكة عيد ميلاد"
 * @bodyParam short_description.en string required Short description in English.
 * @bodyParam short_description.ar string required Short description in Arabic.
 * @bodyParam category_id integer required ID of the category. Example: 2
 * @bodyParam base_price_minor integer required Price in EGP piastres (100 = 1 EGP). Example: 80000
 * @bodyParam is_perishable boolean Whether the product is perishable. Example: true
 * @bodyParam is_made_to_order boolean Whether the product is made to order. Example: true
 * @bodyParam lead_time_hours integer required if is_made_to_order is true. Lead time in hours. Example: 48
 * @bodyParam stock_quantity integer Quantity in stock (null means unlimited). Example: 10
 * @bodyParam customization_fields array nullable Array of customization field definitions.
 * @bodyParam customization_fields.* array Each customization field object.
 */
class CreateSaleServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->user()?->vendorProfile;

        return $vendor?->approvedTypes->contains('product_type', ProductType::Sale) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'short_description' => ['required', 'array'],
            'short_description.en' => ['required', 'string', 'max:500'],
            'short_description.ar' => ['required', 'string', 'max:500'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'base_price_minor' => ['required', 'integer', 'min:0'],
            'is_perishable' => ['boolean'],
            'is_made_to_order' => ['boolean'],
            'lead_time_hours' => ['nullable', 'required_if:is_made_to_order,true', 'integer', 'min:1', 'max:720'],
            'stock_quantity' => ['nullable', 'integer', 'min:1'],
            'customization_fields' => ['nullable', 'array'],
            'customization_fields.*' => ['array'],
        ];
    }
}
