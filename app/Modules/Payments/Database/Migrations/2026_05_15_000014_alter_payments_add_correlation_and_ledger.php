<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            // Shared across the full causal chain
            $table->char('correlation_id', 26)->nullable()->after('status');

            // FK to the transaction group that captured this payment
            $table->unsignedBigInteger('capture_ledger_group_id')->nullable()->after('correlation_id');

            $table->foreign('capture_ledger_group_id', 'payments_capture_group_fk')
                ->references('id')
                ->on('ledger_transaction_groups')
                ->restrictOnDelete();

            $table->index('correlation_id', 'payments_correlation_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign('payments_capture_group_fk');
            $table->dropIndex('payments_correlation_index');
            $table->dropColumn(['correlation_id', 'capture_ledger_group_id']);
        });
    }
};
