<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_digital_details', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // PK is service_id — no auto-increment, no public_id
            $table->unsignedBigInteger('service_id');

            $table->enum('delivery_method', ['email', 'sms', 'whatsapp', 'link'])
                ->default('email');

            $table->boolean('has_expiry')->default(false);

            // Only relevant when has_expiry=true — enforced at application layer
            $table->unsignedSmallInteger('expiry_days_after_purchase')->nullable();

            $table->boolean('is_refundable_after_delivery')->default(false);

            $table->string('redemption_url_template')->nullable();

            // Phase 1.5 extension point — nullable with no FK constraint yet
            $table->unsignedBigInteger('code_pool_id')->nullable();

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
        Schema::dropIfExists('service_digital_details');
    }
};
