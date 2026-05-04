<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVendorReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @bodyParam rating int required Rating between 1 and 5. Example: 4
     * @bodyParam body string nullable Review text (max 2000 chars). Example: "Very professional vendor!"
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
