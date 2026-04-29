<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam name.en string optional (partial update) Service name in English. Example: "Digital Birthday Invitation"
 * @bodyParam name.ar string optional (partial update) Service name in Arabic. Example: "دعوة عيد ميلاد رقمية"
 * @bodyParam short_description.en string optional (partial update) Short description in English.
 * @bodyParam short_description.ar string optional (partial update) Short description in Arabic.
 * @bodyParam category_id integer optional (partial update) ID of the category. Example: 3
 * @bodyParam base_price_minor integer optional (partial update) Price in EGP piastres (100 = 1 EGP). Example: 5000
 * @bodyParam delivery_method string optional (partial update) Delivery channel. Must be one of: email, sms, whatsapp, link. Example: "email"
 * @bodyParam has_expiry boolean optional (partial update) Whether the digital product expires. Example: true
 * @bodyParam expiry_days_after_purchase integer optional (partial update) required if has_expiry is true. Days until expiry. Example: 30
 * @bodyParam is_refundable_after_delivery boolean optional (partial update) Whether refundable after delivery. Example: false
 * @bodyParam redemption_url_template string optional (partial update) URL template for redemption. Example: "https://example.com/redeem/{code}"
 */
class UpdateDigitalServiceRequest extends FormRequest
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
            'name'                        => ['sometimes', 'required', 'array'],
            'name.en'                     => ['sometimes', 'required', 'string', 'max:255'],
            'name.ar'                     => ['sometimes', 'required', 'string', 'max:255'],
            'short_description'           => ['sometimes', 'required', 'array'],
            'short_description.en'        => ['sometimes', 'required', 'string', 'max:500'],
            'short_description.ar'        => ['sometimes', 'required', 'string', 'max:500'],
            'category_id'                 => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'base_price_minor'            => ['sometimes', 'required', 'integer', 'min:0'],
            'delivery_method'             => ['sometimes', 'required', 'string', 'in:email,sms,whatsapp,link'],
            'has_expiry'                  => ['sometimes', 'boolean'],
            'expiry_days_after_purchase'  => ['required_if:has_expiry,true', 'integer', 'min:1', 'max:3650'],
            'is_refundable_after_delivery'=> ['sometimes', 'boolean'],
            'redemption_url_template'     => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}
