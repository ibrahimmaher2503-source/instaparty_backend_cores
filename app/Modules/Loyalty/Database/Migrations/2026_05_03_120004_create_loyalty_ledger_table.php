<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_ledger', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->restrictOnDelete();
            $table->enum('entry_type', ['earn', 'redeem', 'reversal', 'void_release']);
            $table->integer('points'); // signed
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('booking_item_id')->nullable()->constrained('booking_items')->restrictOnDelete();
            $table->foreignId('redemption_id')->nullable()->constrained('loyalty_redemptions')->restrictOnDelete();
            $table->unsignedBigInteger('reversed_from_ledger_id')->nullable();
            $table->enum('product_type', ['rental', 'sale', 'digital'])->nullable();
            $table->json('reason');

            // Append-only: created_at only, no updated_at
            $table->timestamp('created_at')->useCurrent();

            // Partial-unique for idempotent earning: one earn row per booking_item
            $table->string('earn_dedup_key')->virtualAs(
                "CASE WHEN entry_type = 'earn' THEN CAST(booking_item_id AS CHAR) ELSE NULL END"
            )->nullable();

            $table->unique('earn_dedup_key');
            $table->index(['customer_id', 'vendor_profile_id', 'created_at']);
            $table->index(['vendor_profile_id', 'entry_type', 'created_at']);

            $table->foreign('reversed_from_ledger_id')
                ->references('id')
                ->on('loyalty_ledger')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_ledger');
    }
};
