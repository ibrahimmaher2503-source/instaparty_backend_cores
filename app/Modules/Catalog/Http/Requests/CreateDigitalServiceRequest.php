<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam name.en string required Service name in English. Example: "Digital Birthday Invitation"
 * @bodyParam name.ar string required Service name in Arabic. Example: "دعوة عيد ميلاد رقمية"
 * @bodyParam short_description.en string required Short description in English.
 * @bodyParam short_description.ar string required Short description in Arabic.
 * @bodyParam category_id integer required ID of the category. Example: 3
 * @bodyParam base_price_minor integer required Price in EGP piastres (100 = 1 EGP). Example: 5000
 * @bodyParam delivery_method string required Delivery channel. Must be one of: email, sms, whatsapp, link. Example: "email"
 * @bodyParam has_expiry boolean Whether the digital product expires. Example: true
 * @bodyParam expiry_days_after_purchase integer required if has_expiry is true. Days until expiry. Example: 30
 * @bodyParam is_refundable_after_delivery boolean Whether refundable after delivery. Example: false
 * @bodyParam redemption_url_template string nullable URL template for redemption. Example: "https://example.com/redeem/{code}"
 */
class CreateDigitalServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->vendorProfile?->approvedTypes->contains('product_type', 'digital') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'                        => ['required', 'array'],
            'name.en'                     => ['required', 'string', 'max:255'],
            'name.ar'                     => ['required', 'string', 'max:255'],
            'short_description'           => ['required', 'array'],
            'short_description.en'        => ['required', 'string', 'max:500'],
            'short_description.ar'        => ['required', 'string', 'max:500'],
            'category_id'                 => ['required', 'integer', 'exists:categories,id'],
            'base_price_minor'            => ['required', 'integer', 'min:0'],
            'delivery_method'             => ['required', 'string', 'in:email,sms,whatsapp,link'],
            'has_expiry'                  => ['boolean'],
            'expiry_days_after_purchase'  => ['required_if:has_expiry,true', 'integer', 'min:1', 'max:3650'],
            'is_refundable_after_delivery'=> ['boolean'],
            'redemption_url_template'     => ['nullable', 'string', 'max:2048'],
        ];
    }
}
