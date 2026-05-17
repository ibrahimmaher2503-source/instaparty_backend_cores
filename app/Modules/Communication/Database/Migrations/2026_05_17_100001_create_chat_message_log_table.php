<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only mirror of Firestore chat messages.
 *
 * Append-only contract (ADR-0014):
 *  - No `updated_at` column.
 *  - Only `flagged`, `flag_reason`, `redacted` may UPDATE after insert.
 *  - Enforced at three layers: (L1) this migration's schema shape,
 *    (L2) `ChatMessageLog::booted()` `updating` guard,
 *    (L3) `NoChatContentMutationInvariantTest` Pest invariant test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_message_log', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('chat_thread_id')->constrained('chat_threads')->restrictOnDelete();
            $table->string('firestore_message_id', 120);
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->enum('message_kind', ['text', 'image', 'voice', 'system']);
            $table->enum('detected_locale', ['ar', 'en', 'mixed'])->nullable();
            $table->boolean('flagged')->default(false);
            $table->string('flag_reason', 80)->nullable();
            $table->boolean('redacted')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['chat_thread_id', 'firestore_message_id'], 'cml_thread_firestore_unique');
            $table->index(['chat_thread_id', 'created_at'], 'cml_thread_created_idx');
            $table->index(['flagged'], 'cml_flagged_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_log');
    }
};
