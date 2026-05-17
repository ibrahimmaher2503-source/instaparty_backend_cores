<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisement_packages', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->enum('placement_type', ['homepage_banner', 'category_banner', 'search_sponsored', 'featured_listing']);
            $table->unsignedInteger('duration_days');
            $table->unsignedBigInteger('price_minor');
            $table->char('price_currency', 3)->default('EGP');
            $table->unsignedInteger('impression_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['placement_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisement_packages');
    }
};
