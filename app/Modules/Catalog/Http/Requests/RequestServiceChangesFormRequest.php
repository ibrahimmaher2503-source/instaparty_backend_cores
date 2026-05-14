<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestServiceChangesFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.field_path' => ['required', 'string', 'max:255'],
            'items.*.requested_change_en' => ['required', 'string', 'max:1000'],
            'items.*.requested_change_ar' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => __('validation.required'),
            'items.array' => __('validation.array'),
            'items.min' => __('validation.min.array'),
            'items.*.field_path.required' => __('validation.required'),
            'items.*.field_path.string' => __('validation.string'),
            'items.*.field_path.max' => __('validation.max.string'),
            'items.*.requested_change_en.required' => __('validation.required'),
            'items.*.requested_change_en.string' => __('validation.string'),
            'items.*.requested_change_en.max' => __('validation.max.string'),
            'items.*.requested_change_ar.required' => __('validation.required'),
            'items.*.requested_change_ar.string' => __('validation.string'),
            'items.*.requested_change_ar.max' => __('validation.max.string'),
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        return parent::validated($key, $default);
    }
}
