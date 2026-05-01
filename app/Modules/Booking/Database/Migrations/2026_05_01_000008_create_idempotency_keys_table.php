<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('key', 36);
            $table->string('route', 255);
            $table->unsignedBigInteger('user_id');
            $table->json('response');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['key', 'route', 'user_id']);
            $table->index(['key', 'route', 'user_id']);
            $table->index('expires_at');

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
