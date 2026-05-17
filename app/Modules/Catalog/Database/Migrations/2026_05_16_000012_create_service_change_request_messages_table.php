<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only — no updated_at column per migration rules for append-only tables
        Schema::create('service_change_request_messages', function (Blueprint $table): void {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->foreignId('service_change_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('author_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('author_role', ['admin', 'vendor']);

            $table->json('body');

            $table->unsignedTinyInteger('clarification_round');

            $table->timestamp('created_at')->useCurrent();

            // Thread render order
            $table->index(['service_change_request_id', 'created_at'], 'scrm_cr_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_change_request_messages');
    }
};
