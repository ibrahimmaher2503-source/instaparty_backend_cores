<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->string('name', 160);
            $table->string('channel', 20);
            $table->string('target_locale', 10);

            $table->json('segment_filters');
            $table->json('product_type_segment')->nullable();

            $table->json('subject')->nullable();
            $table->json('body');

            $table->timestamp('scheduled_at')->nullable();

            $table->string('status', 30)->default('draft');

            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['channel', 'status']);
            $table->index(['created_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
