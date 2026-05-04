<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only: no updated_at, no deleted_at. Status-only updates allowed.
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('vendor_subscription_id')
                ->constrained('vendor_subscriptions')
                ->restrictOnDelete();

            $table->string('idempotency_key', 64)->unique()->nullable();

            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('billing_cycle', 10);

            $table->unsignedBigInteger('amount_minor');
            $table->char('amount_currency', 3)->default('EGP');

            $table->timestamp('period_start');
            $table->timestamp('period_end');

            $table->string('gateway_ref', 255)->nullable();
            $table->string('checkout_url', 2048)->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['vendor_subscription_id', 'period_start', 'period_end'], 'si_subscription_period_unique');
            $table->index(['vendor_subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
