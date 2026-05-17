<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_change_request_items', function (Blueprint $table): void {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->foreignId('service_change_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('field_path', 191);

            $table->enum('field_classification', [
                'shared',
                'rental',
                'sale',
                'digital',
                'media',
                'availability',
                'pricing_tier',
            ]);

            // NULL allowed for "added" fields (new pricing tier, etc.)
            $table->json('before_value')->nullable();
            // NULL allowed for "removed" fields (deleted blackout date, etc.)
            $table->json('after_value')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Eager load on detail page
            $table->index('service_change_request_id', 'scri_cr_id_idx');

            // Admin diff metrics
            $table->index('field_classification', 'scri_classification_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_change_request_items');
    }
};
