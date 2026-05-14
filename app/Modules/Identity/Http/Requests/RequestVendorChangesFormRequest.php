<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestVendorChangesFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.field_path' => ['required', 'string', 'max:255'],
            'items.*.requested_change_en' => ['required', 'string', 'min:5'],
            'items.*.requested_change_ar' => ['required', 'string', 'min:5'],
        ];
    }
}
