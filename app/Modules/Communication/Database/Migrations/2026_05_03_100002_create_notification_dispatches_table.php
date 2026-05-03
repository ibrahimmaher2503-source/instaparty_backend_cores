<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->unsignedBigInteger('notification_template_id')->nullable();
            $table->foreign('notification_template_id')
                ->references('id')
                ->on('notification_templates')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('channel', 20);
            $table->string('locale', 10)->default('en');
            $table->string('status', 20)->default('queued');

            $table->json('context')->nullable();

            $table->string('provider', 50)->nullable();
            $table->string('provider_ref', 255)->nullable();

            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'status']);
            $table->index(['notification_template_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
