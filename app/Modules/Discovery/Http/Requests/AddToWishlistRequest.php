<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'string', 'exists:services,public_id'],
        ];
    }
}
