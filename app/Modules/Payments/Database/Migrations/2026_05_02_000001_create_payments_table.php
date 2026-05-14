<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('gateway', 40);
            $table->string('gateway_ref', 190);
            $table->unsignedBigInteger('amount_minor');
            $table->char('amount_currency', 3)->default('EGP');
            $table->enum('method', ['card', 'wallet', 'installment', 'cash_on_delivery', 'transfer']);
            $table->enum('status', ['pending', 'authorized', 'captured', 'failed', 'refunded', 'partially_refunded', 'voided', 'abandoned'])
                ->default('pending');
            $table->timestamp('captured_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->json('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_ref']);
            $table->index(['booking_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
