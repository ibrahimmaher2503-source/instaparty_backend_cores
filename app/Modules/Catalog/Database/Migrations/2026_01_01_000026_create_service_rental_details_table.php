<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_rental_details', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // PK is service_id — no auto-increment, no public_id
            $table->unsignedBigInteger('service_id');

            $table->boolean('requires_electricity')->default(false);
            $table->boolean('requires_outdoor_space')->default(false);
            $table->unsignedSmallInteger('default_rental_duration_hours');
            $table->unsignedSmallInteger('setup_time_minutes')->default(0);
            $table->unsignedSmallInteger('teardown_time_minutes')->default(0);
            $table->unsignedBigInteger('security_deposit_minor')->default(0);
            $table->char('security_deposit_currency', 3)->default('EGP');
            $table->unsignedSmallInteger('minimum_space_sqm')->nullable();

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
        Schema::dropIfExists('service_rental_details');
    }
};
