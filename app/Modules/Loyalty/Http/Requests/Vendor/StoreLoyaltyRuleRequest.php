<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam label.en string required Rule label in English. Example: Standard Earn Rate
 * @bodyParam label.ar string required Rule label in Arabic. Example: معدل الكسب القياسي
 * @bodyParam earn_points_per_minor integer required Points earned per earn_minor_per_unit piastres. Example: 1
 * @bodyParam earn_minor_per_unit integer required Minor units per earn cycle (100 = 1 EGP). Example: 100
 * @bodyParam redemption_ratio_points integer required Points needed for redemption_ratio_minor discount. Example: 100
 * @bodyParam redemption_ratio_minor integer required Discount in piastres per redemption_ratio_points. Example: 1000
 * @bodyParam min_points_to_redeem integer Minimum points required to redeem. Default: 0. Example: 200
 * @bodyParam max_redeem_pct_bps integer Max discount as basis points of order total (5000 = 50%). Default: 5000. Example: 2000
 */
class StoreLoyaltyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'                    => ['required', 'array'],
            'label.en'                 => ['required', 'string', 'max:100'],
            'label.ar'                 => ['required', 'string', 'max:100'],
            'earn_points_per_minor'    => ['required', 'integer', 'min:1', 'max:100'],
            'earn_minor_per_unit'      => ['required', 'integer', 'min:1'],
            'redemption_ratio_points'  => ['required', 'integer', 'min:1'],
            'redemption_ratio_minor'   => ['required', 'integer', 'min:1'],
            'min_points_to_redeem'     => ['nullable', 'integer', 'min:0'],
            'max_redeem_pct_bps'       => ['nullable', 'integer', 'min:0', 'max:5000'],
        ];
    }
}
