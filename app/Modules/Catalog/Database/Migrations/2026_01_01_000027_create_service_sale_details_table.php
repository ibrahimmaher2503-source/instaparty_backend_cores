<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_sale_details', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // PK is service_id — no auto-increment, no public_id
            $table->unsignedBigInteger('service_id');

            $table->boolean('is_perishable')->default(false);
            $table->boolean('is_made_to_order')->default(false);

            // Required when is_made_to_order=true — enforced at application layer
            $table->unsignedSmallInteger('lead_time_hours')->nullable();

            // NULL means unlimited stock
            $table->unsignedInteger('stock_quantity')->nullable();

            // Stores customization schema as JSON
            $table->json('customization_fields')->nullable();

            $table->timestamps();

            $table->primary('service_id');
            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_sale_details');
    }
};
