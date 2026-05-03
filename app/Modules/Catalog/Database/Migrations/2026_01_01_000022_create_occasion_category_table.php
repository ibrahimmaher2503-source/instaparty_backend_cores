<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occasion_category', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->foreignId('occasion_id')
                ->constrained('occasions')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['occasion_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occasion_category');
    }
};
