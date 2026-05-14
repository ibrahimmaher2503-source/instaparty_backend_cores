<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam body string required The vendor's response text. Example: "Thank you for your kind review!"
 */
class RespondToReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->vendorProfile !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
