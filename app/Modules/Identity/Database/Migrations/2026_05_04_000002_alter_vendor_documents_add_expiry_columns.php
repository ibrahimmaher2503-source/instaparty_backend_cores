<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            $table->date('expires_at')->nullable()->after('deleted_at');
            $table->tinyInteger('is_critical')->unsigned()->default(0)->after('expires_at');
            $table->date('last_reminder_sent_at')->nullable()->after('is_critical');

            $table->index(['expires_at', 'is_critical'], 'idx_vendor_docs_expiry_critical');
            $table->index(['last_reminder_sent_at'], 'idx_vendor_docs_last_reminder');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            $table->dropIndex('idx_vendor_docs_expiry_critical');
            $table->dropIndex('idx_vendor_docs_last_reminder');
            $table->dropColumn(['expires_at', 'is_critical', 'last_reminder_sent_at']);
        });
    }
};
