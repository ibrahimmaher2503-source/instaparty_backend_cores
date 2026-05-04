<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_admin_interventions', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->enum('intervention_type', ['force_cancel', 'vendor_timeout', 'vendor_proposal', 'admin_note'])->notNullable();
            $table->text('reason');
            $table->json('before_state');
            $table->json('after_state');
            $table->enum('customer_consent_status', ['pending', 'accepted', 'rejected', 'consent_expired'])->nullable();
            $table->unsignedBigInteger('proposed_vendor_id')->nullable();
            $table->foreign('proposed_vendor_id')->references('id')->on('vendor_profiles')->restrictOnDelete();
            $table->timestamp('consent_expires_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'intervention_type']);
            $table->index(['customer_consent_status', 'consent_expires_at']);
            $table->index(['admin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_admin_interventions');
    }
};
