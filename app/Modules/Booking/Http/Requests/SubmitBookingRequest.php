<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SubmitBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [];
    }

    public function idempotencyKey(): string
    {
        $key = $this->header('Idempotency-Key');

        if (! $key || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            throw new HttpResponseException(response()->json([
                'data' => null,
                'meta' => [],
                'errors' => ['Idempotency-Key header is required and must be a valid UUID'],
            ], 422));
        }

        return $key;
    }
}
