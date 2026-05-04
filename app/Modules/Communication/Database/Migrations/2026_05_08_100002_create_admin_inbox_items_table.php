<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_inbox_items', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->unsignedBigInteger('admin_id');
            $table->foreign('admin_id')->references('id')->on('users')->restrictOnDelete();

            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');

            $table->enum('severity', ['info', 'warning', 'critical']);

            $table->json('title');
            $table->json('body');

            $table->enum('status', ['unread', 'read', 'snoozed', 'resolved', 'reassigned'])->default('unread');
            $table->timestamp('snoozed_until')->nullable();

            $table->unsignedBigInteger('assigned_to_admin_id')->nullable();
            $table->foreign('assigned_to_admin_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'admin_id'], 'aii_source_admin_unique');
            $table->index(['admin_id', 'status'], 'aii_admin_status_idx');
            $table->index(['admin_id', 'status', 'snoozed_until'], 'aii_admin_status_snooze_idx');
            $table->index(['status', 'snoozed_until'], 'aii_status_snooze_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_inbox_items');
    }
};
