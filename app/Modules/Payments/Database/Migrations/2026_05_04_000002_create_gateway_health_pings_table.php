<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_health_pings', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->char('gateway_code', 20);
            $table->unsignedInteger('latency_ms');
            $table->boolean('success');
            $table->string('error_message', 500)->nullable();

            // Append-only — no updated_at
            $table->timestamp('checked_at')->useCurrent();

            $table->index(['gateway_code', 'checked_at']);
            $table->index(['success', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_health_pings');
    }
};
