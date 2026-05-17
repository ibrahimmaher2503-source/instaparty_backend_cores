<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_themes_pivot', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->foreignId('service_id')
                ->constrained('services')
                ->cascadeOnDelete();
            $table->foreignId('service_theme_id')
                ->constrained('service_themes')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->primary(['service_id', 'service_theme_id']);
            $table->index(['service_theme_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_themes_pivot');
    }
};
