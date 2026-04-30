<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_customer_notes', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->enum('detected_locale', ['en', 'ar'])->default('ar');

            $table->timestamp('created_at')->useCurrent();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_customer_notes');
    }
};
