<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_field_schemas', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->enum('product_type', ['rental', 'sale', 'digital']);
            $table->string('field_key', 80);
            $table->json('field_label');
            $table->enum('field_type', ['text', 'number', 'boolean', 'select', 'multiselect', 'date']);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->json('validation_rules')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'product_type', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_field_schemas');
    }
};
