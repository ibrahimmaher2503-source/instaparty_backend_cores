<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_inventory_reservations', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Denormalized for query speed
            $table->enum('product_type', ['rental', 'sale', 'digital']);

            $table->enum('hold_type', ['cart', 'payment'])->default('cart');

            $table->enum('status', ['held', 'confirmed', 'expired', 'released'])
                ->default('held');

            // For rental: event window; nullable for sale/digital
            $table->dateTime('reserved_starts_at')->nullable();
            $table->dateTime('reserved_ends_at')->nullable();

            // For sale: units held; 1 for rental/digital
            $table->unsignedSmallInteger('quantity')->default(1);

            // cart: now+15min, payment: now+24h
            $table->dateTime('expires_at');

            // Phase 3.1 will add the FK constraint — column only for now
            $table->unsignedBigInteger('booking_item_id')->nullable();

            $table->timestamps();

            // Rental overlap query
            $table->index(['service_id', 'reserved_starts_at', 'reserved_ends_at', 'status']);

            // Cleanup job
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_inventory_reservations');
    }
};
