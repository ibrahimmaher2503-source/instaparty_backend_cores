<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_coverage_areas', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->foreignId('vendor_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('delivery_fee_minor')->default(0);
            $table->char('delivery_fee_currency', 3)->default('EGP');
            $table->unsignedBigInteger('min_order_minor')->default(0);
            $table->char('min_order_currency', 3)->default('EGP');
            $table->timestamps();

            $table->unique(['vendor_profile_id', 'city_id']);
            $table->index(['city_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_coverage_areas');
    }
};
