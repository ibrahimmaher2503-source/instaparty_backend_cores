<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Requests\Vendor;

use App\Modules\Loyalty\Domain\Enums\RuleKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoyaltyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'array'],
            'name.en' => ['sometimes', 'string', 'max:150'],
            'name.ar' => ['sometimes', 'string', 'max:150'],
            'terms' => ['sometimes', 'nullable', 'array'],
            'terms.en' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'terms.ar' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'points_per_currency_unit' => ['sometimes', 'numeric', 'min:0.0001', 'max:1000'],
            'points_value_minor' => ['sometimes', 'integer', 'min:1'],
            'points_value_currency' => ['sometimes', 'string', 'size:3'],
            'min_points_to_redeem' => ['sometimes', 'integer', 'min:1'],
            'max_redeem_pct' => ['sometimes', 'integer', 'min:0', 'max:100'],
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
