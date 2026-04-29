<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Owned by Catalog for Phase 2.4.
        // Module ownership will be reviewed when the Imports module is introduced in Phase 6.1.
        Schema::create('excel_imports', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('vendor_profile_id')
                ->constrained('vendor_profiles')
                ->restrictOnDelete();

            $table->enum('product_type', ['rental', 'sale', 'digital']);

            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending');

            $table->string('original_filename');
            $table->string('stored_path');

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_imports');
    }
};
