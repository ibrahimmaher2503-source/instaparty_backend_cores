<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorTransitionBookingItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->vendorProfile !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The four advancement lanes; report_issue has its own flow.
            'lane' => ['required', Rule::in(['preparing', 'ready', 'in_progress', 'completed'])],
            'completion_note' => ['nullable', 'string', 'max:500'],
            'completion_photo' => ['nullable', 'image', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lane.required' => __('booking::booking.validation.lane_required'),
            'lane.in' => __('booking::booking.validation.lane_invalid'),
        ];
    }
}
