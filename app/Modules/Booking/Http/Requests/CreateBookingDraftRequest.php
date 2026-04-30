<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Application\DTOs\CreateBookingDraftDTO;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class CreateBookingDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occasion_id' => ['required', 'string', 'exists:occasions,public_id'],
            'event_starts_at' => ['required', 'date', 'after:now'],
            'event_ends_at' => ['required', 'date', 'after:event_starts_at'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'theme' => ['nullable', 'array'],
            'theme.en' => ['nullable', 'string', 'max:120'],
            'theme.ar' => ['nullable', 'string', 'max:120'],
            'celebrant_name' => ['nullable', 'string', 'max:120'],
            'celebrant_dob' => ['nullable', 'date'],
            'celebrant_gender' => ['nullable', 'in:male,female,other'],
            'address' => ['required', 'array'],
            'address.city_id' => ['required', 'string', 'exists:cities,public_id'],
            'address.address_line' => ['required', 'string', 'max:255'],
            'address.building' => ['nullable', 'string', 'max:80'],
            'address.floor' => ['nullable', 'string', 'max:20'],
            'address.apartment' => ['nullable', 'string', 'max:20'],
            'address.landmark' => ['nullable', 'string', 'max:255'],
            'address.recipient_name' => ['required', 'string', 'max:120'],
            'address.recipient_phone_e164' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
        ];
    }

    public function toDTO(): CreateBookingDraftDTO
    {
        $occasionId = DB::table('occasions')
            ->where('public_id', $this->input('occasion_id'))
            ->value('id');

        $cityId = DB::table('cities')
            ->where('public_id', $this->input('address.city_id'))
            ->value('id');

        return new CreateBookingDraftDTO(
            customerId: (int) auth()->id(),
            occasionId: (int) $occasionId,
            eventStartsAt: Carbon::parse($this->input('event_starts_at')),
            eventEndsAt: Carbon::parse($this->input('event_ends_at')),
            guestCount: $this->input('guest_count'),
            theme: $this->input('theme'),
            celebrantName: $this->input('celebrant_name'),
            celebrantDob: $this->input('celebrant_dob'),
            celebrantGender: $this->input('celebrant_gender'),
            addressCityId: (int) $cityId,
            addressLine: $this->input('address.address_line'),
            addressBuilding: $this->input('address.building'),
            addressFloor: $this->input('address.floor'),
            addressApartment: $this->input('address.apartment'),
            addressLandmark: $this->input('address.landmark'),
            addressLatitude: $this->input('address.latitude'),
            addressLongitude: $this->input('address.longitude'),
            recipientName: $this->input('address.recipient_name'),
            recipientPhoneE164: $this->input('address.recipient_phone_e164'),
        );
    }
}
