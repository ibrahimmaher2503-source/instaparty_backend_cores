<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam name.en string required Service name in English. Example: "Birthday Bounce House"
 * @bodyParam name.ar string required Service name in Arabic. Example: "بيت الارتداد"
 * @bodyParam short_description.en string required Short description in English.
 * @bodyParam short_description.ar string required Short description in Arabic.
 * @bodyParam category_id integer required ID of the category. Example: 1
 * @bodyParam base_price_minor integer required Price in EGP piastres (100 = 1 EGP). Example: 150000
 * @bodyParam requires_electricity boolean Whether requires electricity. Example: true
 * @bodyParam requires_outdoor_space boolean Whether requires outdoor space. Example: false
 * @bodyParam default_rental_duration_hours integer required Default rental window in hours. Example: 6
 * @bodyParam setup_time_minutes integer Setup time in minutes. Example: 60
 * @bodyParam teardown_time_minutes integer Teardown time in minutes. Example: 30
 * @bodyParam security_deposit_minor integer Refundable security deposit in piastres. Example: 50000
 * @bodyParam minimum_space_sqm integer Minimum floor space required in square metres. Example: 25
 */
class CreateRentalServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->vendorProfile?->approvedTypes->contains('product_type', 'rental') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'                          => ['required', 'array'],
            'name.en'                       => ['required', 'string', 'max:255'],
            'name.ar'                       => ['required', 'string', 'max:255'],
            'short_description'             => ['required', 'array'],
            'short_description.en'          => ['required', 'string', 'max:500'],
            'short_description.ar'          => ['required', 'string', 'max:500'],
            'category_id'                   => ['required', 'integer', 'exists:categories,id'],
            'base_price_minor'              => ['required', 'integer', 'min:0'],
            'requires_electricity'          => ['boolean'],
            'requires_outdoor_space'        => ['boolean'],
            'default_rental_duration_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'setup_time_minutes'            => ['integer', 'min:0', 'max:1440'],
            'teardown_time_minutes'         => ['integer', 'min:0', 'max:1440'],
            'security_deposit_minor'        => ['integer', 'min:0'],
            'minimum_space_sqm'             => ['nullable', 'integer', 'min:1'],
        ];
    }
}
