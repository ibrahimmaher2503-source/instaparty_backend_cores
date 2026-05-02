<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'attempt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
