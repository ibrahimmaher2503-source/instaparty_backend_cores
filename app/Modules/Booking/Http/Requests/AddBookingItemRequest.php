<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class AddBookingItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'string', 'exists:services,public_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'effective_starts_at' => ['nullable', 'date'],
            'effective_ends_at' => ['nullable', 'date', 'after:effective_starts_at'],
            'customization_data' => ['nullable', 'array'],
        ];
    }

    public function toDTO(int $bookingId, int $customerId): AddBookingItemDTO
    {
        $serviceId = app(CatalogServiceReader::class)->resolvePublicId($this->input('service_id'));

        return new AddBookingItemDTO(
            bookingId: $bookingId,
            customerId: $customerId,
            serviceId: (int) $serviceId,
            quantity: (int) $this->input('quantity'),
            effectiveStartsAt: $this->filled('effective_starts_at')
                ? Carbon::parse($this->input('effective_starts_at'))
                : null,
            effectiveEndsAt: $this->filled('effective_ends_at')
                ? Carbon::parse($this->input('effective_ends_at'))
                : null,
            customizationData: $this->input('customization_data'),
        );
    }
}
