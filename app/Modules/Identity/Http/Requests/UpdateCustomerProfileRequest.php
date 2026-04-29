<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Application\DTOs\UpdateCustomerProfileDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'preferred_locale' => ['sometimes', 'string', 'in:en,ar'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female,prefer_not_to_say'],
            'accepts_marketing' => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): UpdateCustomerProfileDTO
    {
        return UpdateCustomerProfileDTO::fromArray($this->validated());
    }
}
