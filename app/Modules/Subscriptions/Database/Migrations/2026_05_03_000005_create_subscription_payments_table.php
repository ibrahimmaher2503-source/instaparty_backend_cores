<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only + insert-only except status on latest row.
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->foreignId('subscription_invoice_id')
                ->constrained('subscription_invoices')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->enum('mode', ['vendor_initiated', 'recurring_token'])->default('vendor_initiated');
            $table->enum('status', ['pending', 'captured', 'failed'])->default('pending');

            $table->string('gateway', 30)->nullable();
            $table->string('gateway_ref', 255)->nullable();

            $table->string('failure_code', 60)->nullable();
            $table->string('failure_reason', 500)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['subscription_invoice_id', 'attempt_no']);

            // Partial unique: when gateway + gateway_ref are set, pair must be unique
            $table->index(['gateway', 'gateway_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
