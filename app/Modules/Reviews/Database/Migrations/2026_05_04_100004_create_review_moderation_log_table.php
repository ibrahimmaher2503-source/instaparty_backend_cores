<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_moderation_log', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('review_type', 80);
            $table->unsignedBigInteger('review_id');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('moderator_id')->constrained('users')->restrictOnDelete();
            $table->json('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['review_type', 'review_id', 'created_at']);
            $table->index(['moderator_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_moderation_log');
    }
};
