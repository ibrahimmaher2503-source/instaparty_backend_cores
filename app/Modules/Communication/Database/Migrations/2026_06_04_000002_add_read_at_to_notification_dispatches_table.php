<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (2026-06-04): customer in-app notifications inbox (F17).
 * read_at tracks per-dispatch read state for the in_app channel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable()->after('status');
            $table->index(['user_id', 'channel', 'read_at'], 'idx_notif_dispatch_user_chan_read');
        });
    }

    public function down(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->dropIndex('idx_notif_dispatch_user_chan_read');
            $table->dropColumn('read_at');
        });
    }
};
