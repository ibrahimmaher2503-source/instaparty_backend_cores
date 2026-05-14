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

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();

            $table->unsignedInteger('points_redeemed');
            $table->unsignedBigInteger('amount_minor');
            $table->char('amount_currency', 3)->default('EGP');

            $table->timestamp('created_at')->useCurrent();

            $table->unique('booking_id', 'loyalty_redemptions_booking_unique');
            $table->index(
                ['user_id', 'vendor_profile_id', 'created_at'],
                'loyalty_redemptions_user_vendor_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemptions');
    }
};
