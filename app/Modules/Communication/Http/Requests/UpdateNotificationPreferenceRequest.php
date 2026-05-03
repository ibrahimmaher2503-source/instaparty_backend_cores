<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests;

use App\Modules\Communication\Domain\Enums\EventCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /**
             * @bodyParam is_enabled boolean required Whether to enable this channel for this event category. Example: true
             */
            'is_enabled' => ['required', 'boolean'],

            /**
             * @bodyParam quiet_hours_start string optional Quiet hours start time (HH:MM, user timezone). Example: "22:00"
             */
            'quiet_hours_start' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],

            /**
             * @bodyParam quiet_hours_end string optional Quiet hours end time (HH:MM). Required when quiet_hours_start is provided. Example: "08:00"
             */
            'quiet_hours_end' => ['nullable', 'regex:/^\d{2}:\d{2}$/', 'required_with:quiet_hours_start'],

            /**
             * @bodyParam timezone string optional IANA timezone for quiet hours. Example: "Africa/Cairo"
             */
            'timezone' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $eventCategory = $this->route('event_category');
            $isEnabled = $this->boolean('is_enabled');

            if ($eventCategory === EventCategory::System->value && ! $isEnabled) {
                $validator->errors()->add(
                    'is_enabled',
                    trans('communication::validation.system_notifications_cannot_be_disabled')
                );
            }
        });
    }
}
