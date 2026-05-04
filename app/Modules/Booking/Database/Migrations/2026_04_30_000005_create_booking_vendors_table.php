<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_vendors', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->restrictOnDelete();

            $table->enum('sub_status', [
                'pending', 'accepted', 'modified', 'rejected', 'cancelled', 'in_progress', 'completed',
            ])->default('pending');

            $table->dateTime('response_deadline')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->json('rejection_reason')->nullable();
            $table->json('vendor_notes')->nullable();

            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->char('subtotal_currency', 3)->default('EGP');
            $table->unsignedBigInteger('delivery_fee_minor')->default(0);
            $table->char('delivery_fee_currency', 3)->default('EGP');
            $table->unsignedBigInteger('commission_minor')->default(0);
            $table->char('commission_currency', 3)->default('EGP');
            $table->unsignedBigInteger('vendor_payout_minor')->default(0);
            $table->char('vendor_payout_currency', 3)->default('EGP');

            $table->timestamps();

            $table->unique(['booking_id', 'vendor_profile_id']);
            $table->index(['vendor_profile_id', 'sub_status', 'response_deadline'], 'bv_vendor_status_deadline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_vendors');
    }
};
