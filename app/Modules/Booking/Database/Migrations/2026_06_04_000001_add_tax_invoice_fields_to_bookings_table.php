<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 ruling #5 (2026-06-04): foundational tax-invoice capture only.
 * Stores the customer's request + invoice identity fields on the booking.
 * NO PDF generation, NO tax-authority integration (Phase 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->boolean('requires_tax_invoice')->default(false);
            $table->string('invoice_name', 255)->nullable()->after('requires_tax_invoice');
            $table->string('invoice_tax_id', 100)->nullable()->after('invoice_name');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['requires_tax_invoice', 'invoice_name', 'invoice_tax_id']);
        });
    }
};
