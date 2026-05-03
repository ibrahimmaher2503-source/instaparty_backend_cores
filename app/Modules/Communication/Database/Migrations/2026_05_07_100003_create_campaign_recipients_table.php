<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->unsignedBigInteger('campaign_run_id');
            $table->foreign('campaign_run_id')->references('id')->on('campaign_runs')->cascadeOnDelete();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();

            $table->unsignedBigInteger('dispatch_id')->nullable();
            $table->foreign('dispatch_id')->references('id')->on('notification_dispatches')->restrictOnDelete()->nullOnDelete();

            $table->string('status', 20)->default('queued');

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['campaign_run_id', 'user_id']);
            $table->index(['campaign_run_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
