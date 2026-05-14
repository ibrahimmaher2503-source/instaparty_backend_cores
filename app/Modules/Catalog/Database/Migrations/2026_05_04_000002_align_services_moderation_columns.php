<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE services MODIFY status ENUM('draft', 'pending_review', 'changes_requested', 'published', 'rejected', 'archived') NOT NULL DEFAULT 'draft'"
            );
        }

        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'moderation_notes')) {
                $table->json('moderation_notes')->nullable()->after('status');
            }

            if (! Schema::hasColumn('services', 'moderated_at')) {
                $table->timestamp('moderated_at')->nullable()->after('moderation_notes');
            }

            if (! Schema::hasColumn('services', 'moderated_by')) {
                $table->foreignId('moderated_by')
                    ->nullable()
                    ->after('moderated_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            $table->index(['product_type', 'status'], 'services_product_type_status_index');
        });
    }

    public function down(): void
    {
        DB::table('services')
            ->where('status', 'rejected')
            ->update(['status' => 'archived']);

        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_product_type_status_index');

            if (Schema::hasColumn('services', 'moderated_by')) {
                $table->dropConstrainedForeignId('moderated_by');
            }

            foreach (['moderated_at', 'moderation_notes'] as $column) {
                if (Schema::hasColumn('services', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE services MODIFY status ENUM('draft', 'pending_review', 'changes_requested', 'published', 'archived') NOT NULL DEFAULT 'draft'"
            );
        }
    }
};
