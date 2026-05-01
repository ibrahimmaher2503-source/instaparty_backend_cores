<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excel_import_errors', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->foreignId('excel_import_id')
                ->constrained('excel_imports')
                ->cascadeOnDelete();

            $table->unsignedInteger('row_number');

            $table->string('field')->nullable();

            // Translatable: {"en": "...", "ar": "..."}
            $table->json('message');

            // Append-only: errors are immutable once written — no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['excel_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_import_errors');
    }
};
