<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 12.10 — foundational tax-invoice request (ruling #5): stores the flag +
 * invoice identity on the booking and audit-logs the request. NO PDF
 * generation, NO tax-authority integration (Phase 2).
 */
class RequestTaxInvoiceAction
{
    public function execute(Booking $booking, int $customerId, string $invoiceName, string $invoiceTaxId): Booking
    {
        return DB::transaction(function () use ($booking, $customerId, $invoiceName, $invoiceTaxId): Booking {
            $booking->forceFill([
                'requires_tax_invoice' => true,
                'invoice_name' => $invoiceName,
                'invoice_tax_id' => $invoiceTaxId,
            ])->save();

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'user_id' => $customerId,
                'action' => 'customer_requested_tax_invoice',
                'changes' => json_encode(['invoice_name' => $invoiceName, 'invoice_tax_id' => $invoiceTaxId]),
                'created_at' => now(),
            ]);

            return $booking;
        });
    }
}
