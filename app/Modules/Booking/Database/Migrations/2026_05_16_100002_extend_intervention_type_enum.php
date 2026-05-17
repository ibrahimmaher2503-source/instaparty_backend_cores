<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE booking_admin_interventions
             MODIFY COLUMN intervention_type
             ENUM('force_cancel', 'vendor_timeout', 'vendor_proposal', 'admin_note', 'vendor_reminder', 'chat_frozen', 'chat_resumed', 'customer_review_reminder')
             NOT NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // WARNING: Rolling back this migration will fail if any rows contain
        // 'vendor_reminder', 'chat_frozen', 'chat_resumed', or 'customer_review_reminder'.
        // Ensure no such rows exist before running migrate:rollback.
        DB::statement(
            "ALTER TABLE booking_admin_interventions
             MODIFY COLUMN intervention_type
             ENUM('force_cancel', 'vendor_timeout', 'vendor_proposal', 'admin_note')
             NOT NULL"
        );
    }
};
