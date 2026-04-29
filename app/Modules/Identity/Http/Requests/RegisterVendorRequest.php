<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Application\DTOs\RegisterVendorDTO;
use App\Modules\Identity\Domain\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_e164' => ['required', 'string', 'regex:/^\+\d{8,15}$/', Rule::unique('users', 'phone_e164')->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'business_name' => ['required', 'array'],
            'business_name.en' => ['required', 'string', 'max:255'],
            'business_name.ar' => ['required', 'string', 'max:255'],
            'business_type' => ['required', 'string', Rule::in(array_column(BusinessType::cases(), 'value'))],
            'primary_governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'primary_city_id' => ['required', 'integer', 'exists:cities,id'],
            'preferred_locale' => ['nullable', 'string', 'in:en,ar'],
        ];
    }

    public function toDTO(): RegisterVendorDTO
    {
        return RegisterVendorDTO::fromArray($this->validated());
    }
}
