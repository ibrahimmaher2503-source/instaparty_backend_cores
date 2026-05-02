<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query', 255);
            $table->enum('locale', ['en', 'ar']);
            $table->json('filters');
            $table->unsignedInteger('results_count')->default(0);
            $table->foreignId('clicked_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            // Append-only: no updated_at, no deleted_at
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
