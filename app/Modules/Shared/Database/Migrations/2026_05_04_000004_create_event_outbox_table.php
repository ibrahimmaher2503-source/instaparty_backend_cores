<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_outbox', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->string('event_key', 120);
            $table->unsignedBigInteger('source_id');
            $table->string('source_type', 255);
            $table->char('dedupe_hash', 32)->index();
            $table->json('payload');
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'dead'])->default('pending');
            $table->timestamp('next_retry_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index(['status', 'next_retry_at']);
            $table->index(['source_type', 'source_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_outbox');
    }
};
