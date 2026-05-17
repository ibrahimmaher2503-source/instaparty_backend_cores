<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->index('lifecycle_status', 'bk_lifecycle_status_idx');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'pay_status_created_at_idx');
        });

        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'nd_status_created_at_idx');
        });

        Schema::table('admin_inbox_items', function (Blueprint $table): void {
            $table->index(['severity', 'status'], 'ai_severity_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bk_lifecycle_status_idx');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('pay_status_created_at_idx');
        });

        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->dropIndex('nd_status_created_at_idx');
        });

        Schema::table('admin_inbox_items', function (Blueprint $table): void {
            $table->dropIndex('ai_severity_status_idx');
        });
    }
};
