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
        DB::statement("ALTER TABLE booking_vendors MODIFY COLUMN sub_status ENUM('pending','accepted','modified','rejected','cancelled','in_progress','completed','timed_out') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE booking_vendors MODIFY COLUMN sub_status ENUM('pending','accepted','modified','rejected','cancelled','in_progress','completed') NOT NULL");
    }
};
