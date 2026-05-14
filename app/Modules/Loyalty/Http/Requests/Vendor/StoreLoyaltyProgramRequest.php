<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Requests\Vendor;

use App\Modules\Loyalty\Domain\Enums\RuleKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @bodyParam name.en string required Program name in English.
 * @bodyParam name.ar string required Program name in Arabic.
 * @bodyParam terms.en string nullable Terms in English.
 * @bodyParam terms.ar string nullable Terms in Arabic.
 * @bodyParam is_active boolean Active flag.
 * @bodyParam points_per_currency_unit number Points per 1 currency unit (e.g. 1 point per EGP).
 * @bodyParam points_value_minor integer Value of 1 point in minor units.
 * @bodyParam points_value_currency string 3-letter currency code.
 * @bodyParam min_points_to_redeem integer Minimum points the customer must hold to redeem.
 * @bodyParam max_redeem_pct integer 0..100 cap on % of order eligible for redemption.
 * @bodyParam points_expire_after_days integer nullable Days before earned points expire (null = never).
 * @bodyParam rules array nullable Optional array of rule drafts.
 */
class StoreLoyaltyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:150'],
            'name.ar' => ['required', 'string', 'max:150'],
            'terms' => ['sometimes', 'nullable', 'array'],
            'terms.en' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'terms.ar' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'points_per_currency_unit' => ['required', 'numeric', 'min:0.0001', 'max:1000'],
            'points_value_minor' => ['required', 'integer', 'min:1'],
            'points_value_currency' => ['sometimes', 'string', 'size:3'],
            'min_points_to_redeem' => ['required', 'integer', 'min:1'],
            'max_redeem_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'points_expire_after_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'rules' => ['sometimes', 'nullable', 'array'],
            'rules.*.rule_kind' => ['required_with:rules', Rule::enum(RuleKind::class)],
            'rules.*.multiplier' => ['required_with:rules', 'numeric', 'min:0.0001', 'max:1000'],
            'rules.*.label' => ['required_with:rules', 'array'],
            'rules.*.label.en' => ['required_with:rules', 'string', 'max:150'],
            'rules.*.label.ar' => ['required_with:rules', 'string', 'max:150'],
            'rules.*.conditions' => ['sometimes', 'nullable', 'array'],
            'rules.*.is_active' => ['sometimes', 'boolean'],
            'rules.*.starts_at' => ['sometimes', 'nullable', 'date'],
            'rules.*.ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:rules.*.starts_at'],
        ];
    }
}
