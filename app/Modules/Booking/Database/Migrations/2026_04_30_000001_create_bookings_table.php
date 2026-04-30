<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->string('reference_no', 20)->unique()->nullable();

            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('occasion_id')->constrained('occasions')->restrictOnDelete();

            $table->enum('lifecycle_status', [
                'draft', 'submitted', 'vendor_review', 'customer_review',
                'confirmed', 'active', 'completed', 'cancelled',
            ])->default('draft');

            $table->enum('payment_status', [
                'unpaid', 'partial', 'paid', 'refund_pending', 'partially_refunded', 'refunded',
            ])->default('unpaid');

            $table->enum('fulfillment_status', [
                'not_started', 'in_progress', 'partially_completed', 'completed', 'failed',
            ])->default('not_started');

            $table->dateTime('event_starts_at')->nullable();
            $table->dateTime('event_ends_at')->nullable();
            $table->unsignedSmallInteger('guest_count')->nullable();
            $table->json('theme')->nullable();
            $table->string('celebrant_name', 120)->nullable();
            $table->date('celebrant_dob')->nullable();
            $table->enum('celebrant_gender', ['male', 'female', 'other'])->nullable();

            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->char('subtotal_currency', 3)->default('EGP');
            $table->unsignedBigInteger('delivery_total_minor')->default(0);
            $table->char('delivery_total_currency', 3)->default('EGP');
            $table->unsignedBigInteger('discount_total_minor')->default(0);
            $table->char('discount_total_currency', 3)->default('EGP');
            $table->unsignedBigInteger('loyalty_redeemed_minor')->default(0);
            $table->char('loyalty_redeemed_currency', 3)->default('EGP');
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->char('total_currency', 3)->default('EGP');
            $table->unsignedBigInteger('amount_paid_minor')->default(0);
            $table->char('amount_paid_currency', 3)->default('EGP');

            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'lifecycle_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
