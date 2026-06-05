<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Customer;

use App\Modules\Booking\Application\Actions\RequestTaxInvoiceAction;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer - Bookings
 */
class TaxInvoiceRequestController
{
    public function __invoke(Request $request, string $bookingPublicId, RequestTaxInvoiceAction $action): JsonResponse
    {
        $validated = $request->validate([
            'invoice_name' => ['required', 'string', 'max:255'],
            'invoice_tax_id' => ['required', 'string', 'max:100'],
        ]);

        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        abort_if($booking === null || $booking->customer_id !== auth()->id(), 404, 'Not found');

        $booking = $action->execute($booking, (int) auth()->id(), $validated['invoice_name'], $validated['invoice_tax_id']);

        return ApiResponse::success([
            'requires_tax_invoice' => true,
            'invoice_name' => $booking->invoice_name,
            'invoice_tax_id' => $booking->invoice_tax_id,
        ]);
    }
}
