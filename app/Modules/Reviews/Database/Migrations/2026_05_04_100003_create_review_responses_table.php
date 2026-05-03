<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_responses', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->enum('review_type', ['service', 'vendor']);
            $table->unsignedBigInteger('review_id');
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->text('body');
            $table->enum('locale', ['ar', 'en', 'mixed'])->default('ar');
            $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'hidden'])->default('pending');
            $table->timestamps();

            $table->index(['review_type', 'review_id']);
            $table->index(['vendor_profile_id', 'moderation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_responses');
    }
};
