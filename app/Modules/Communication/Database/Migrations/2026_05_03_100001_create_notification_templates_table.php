<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->string('event_key');
            $table->string('channel', 20);
            $table->string('audience', 20);

            $table->json('body');
            $table->json('subject')->nullable();
            $table->json('variables')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['event_key', 'channel', 'audience']);

            $table->index(['channel', 'audience', 'is_active']);

            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete()->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->restrictOnDelete()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
