<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_modifications', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('booking_vendor_id')->constrained('booking_vendors')->restrictOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->enum('proposal_kind', ['add_item', 'remove_item', 'change_quantity', 'change_price', 'change_slot', 'add_surcharge', 'add_note']);
            $table->enum('status', ['draft', 'pending', 'customer_accepted', 'customer_rejected', 'withdrawn', 'expired'])->default('pending');
            $table->timestamp('customer_decision_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('vendor_explanation')->nullable();
            $table->json('diff_snapshot');
            $table->timestamps();

            $table->index(['booking_vendor_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_modifications');
    }
};
