<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_reviews', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->foreignId('booking_vendor_id')->constrained('booking_vendors')->restrictOnDelete()->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->tinyInteger('rating')->unsigned();
            $table->text('body')->nullable();
            $table->enum('locale', ['ar', 'en', 'mixed'])->default('ar');
            $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'hidden'])->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_profile_id', 'moderation_status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_reviews');
    }
};
