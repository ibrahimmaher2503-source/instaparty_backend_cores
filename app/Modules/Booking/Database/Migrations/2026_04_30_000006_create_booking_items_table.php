<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_items', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('booking_vendor_id')->constrained('booking_vendors')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();

            $table->enum('product_type', ['rental', 'sale', 'digital']);
            $table->json('name_snapshot');

            $table->unsignedBigInteger('unit_price_minor')->default(0);
            $table->char('unit_price_currency', 3)->default('EGP');
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->char('line_total_currency', 3)->default('EGP');
            $table->unsignedBigInteger('commission_minor')->default(0);
            $table->char('commission_currency', 3)->default('EGP');

            $table->unsignedSmallInteger('quantity')->default(1);
            $table->dateTime('effective_starts_at')->nullable();
            $table->dateTime('effective_ends_at')->nullable();
            $table->boolean('has_item_slot_override')->default(false);
            $table->json('customization_data')->nullable();
            $table->json('type_snapshot')->nullable();
            $table->json('fulfillment_data')->nullable();

            $table->string('item_status', 40);
            $table->unsignedInteger('commission_bps')->default(0);

            $table->timestamps();

            $table->index('booking_vendor_id');
            $table->index('service_id');
            $table->index(['product_type', 'item_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
