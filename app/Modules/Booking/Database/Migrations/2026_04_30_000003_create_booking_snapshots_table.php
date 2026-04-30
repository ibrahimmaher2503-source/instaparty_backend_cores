<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_snapshots', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');

            $table->enum('trigger_kind', [
                'booking_created', 'item_added', 'item_removed', 'booking_submitted',
                'booking_confirmed', 'booking_cancelled', 'vendor_responded', 'payment_captured',
            ]);

            $table->string('trigger_reference_type', 120)->nullable();
            $table->unsignedBigInteger('trigger_reference_id')->nullable();

            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['booking_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_snapshots');
    }
};
