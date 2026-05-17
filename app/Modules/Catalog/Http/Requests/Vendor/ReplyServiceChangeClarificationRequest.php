<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class ReplyServiceChangeClarificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership checked in controller
    }

    public function rules(): array
    {
        return [
            'body'    => ['required', 'array'],
            'body.en' => ['required', 'string', 'max:4000'],
            'body.ar' => ['required', 'string', 'max:4000'],
        ];
    }
}
