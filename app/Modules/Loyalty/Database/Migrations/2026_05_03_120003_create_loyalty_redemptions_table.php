<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_redemptions', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->restrictOnDelete();
            $table->foreignId('loyalty_rule_id')->constrained('loyalty_rules')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->unsignedInteger('points_held');
            $table->unsignedBigInteger('discount_minor');
            $table->char('discount_currency', 3)->default('EGP');
            $table->string('status')->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            // Enforce at most one active (pending or applied) redemption per booking
            // via generated column trick for partial uniqueness
            $table->string('active_booking_key')->virtualAs(
                "CASE WHEN status IN ('pending','applied') THEN CAST(booking_id AS CHAR) ELSE NULL END"
            )->nullable();

            $table->unique('active_booking_key');
            $table->index(['customer_id', 'vendor_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemptions');
    }
};
