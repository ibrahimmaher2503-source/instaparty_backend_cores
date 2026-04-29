<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('vendor_profile_id')
                ->constrained('vendor_profiles')
                ->restrictOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->enum('product_type', ['rental', 'sale', 'digital']);

            $table->json('name');
            $table->json('short_description');

            $table->string('slug');

            $table->enum('status', ['draft', 'pending_review', 'published', 'archived'])
                ->default('draft');

            $table->unsignedBigInteger('base_price_minor');
            $table->char('base_price_currency', 3)->default('EGP');

            $table->boolean('is_featured')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Composite unique: one slug per vendor
            $table->unique(['vendor_profile_id', 'slug']);

            // Catalog browse index
            $table->index(['category_id', 'product_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
