<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moderation flags raised against chat messages (auto-detected or admin-marked).
 *
 * UNIQUE (chat_message_log_id, flag_type) implements idempotency for
 * `DetectSuspiciousMessageJob` and `MarkOffPlatformContactAttemptAction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_moderation_flags', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('chat_message_log_id')->constrained('chat_message_log')->cascadeOnDelete();
            $table->enum('flag_type', ['phone', 'email', 'profanity', 'external_link', 'other']);
            $table->string('matched_pattern', 255)->nullable();
            $table->enum('action_taken', ['redact', 'warn', 'block', 'none'])->default('warn');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['chat_message_log_id', 'flag_type'], 'cmf_log_type_unique');
            $table->index(['reviewed_at'], 'cmf_reviewed_idx');
            $table->index(['flag_type', 'reviewed_at'], 'cmf_type_reviewed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_moderation_flags');
    }
};
