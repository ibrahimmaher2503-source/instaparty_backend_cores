<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceResubmitFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'changed_fields' => ['required', 'array'],
            'changed_fields.*' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'changed_fields.required' => __('validation.required'),
            'changed_fields.array' => __('validation.array'),
        ];
    }

    public function getChangedFields(): array
    {
        return $this->input('changed_fields', []);
    }
}
