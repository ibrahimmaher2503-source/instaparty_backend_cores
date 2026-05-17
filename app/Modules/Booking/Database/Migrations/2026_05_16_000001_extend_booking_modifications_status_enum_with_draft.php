<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces one open draft modification per booking_vendor.
 *
 * NOTE — the enum extension itself is also written back into the original
 * create migration (`2026_05_01_000009`) so fresh migrations include
 * `'draft'` from the start. On MySQL environments that already applied the
 * original migration with the narrower enum, this migration also widens the
 * enum via ALTER TABLE MODIFY. SQLite cannot alter CHECK constraints in
 * place, so on SQLite the wider enum comes purely from the original
 * migration replay; this migration only adds the partial unique index.
 *
 * MySQL 8: emulate a partial unique index via a generated `draft_slot`
 * column + UNIQUE (MySQL does not support partial unique indexes natively).
 *
 * SQLite: use native partial UNIQUE INDEX with WHERE clause.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Idempotent enum widening for already-deployed environments.
            DB::statement(
                'ALTER TABLE booking_modifications '
                ."MODIFY status ENUM('draft','pending','customer_accepted','customer_rejected','withdrawn','expired') "
                ."NOT NULL DEFAULT 'pending'"
            );

            DB::statement(
                'ALTER TABLE booking_modifications '
                ."ADD COLUMN draft_slot TINYINT UNSIGNED AS (CASE WHEN status = 'draft' THEN 1 ELSE NULL END) STORED"
            );

            Schema::table('booking_modifications', function ($table): void {
                $table->unique(['booking_vendor_id', 'draft_slot'], 'bk_modifs_one_draft_per_vendor');
            });

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX bk_modifs_one_draft_per_vendor '
                .'ON booking_modifications (booking_vendor_id) '
                ."WHERE status = 'draft'"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('booking_modifications', function ($table): void {
                $table->dropUnique('bk_modifs_one_draft_per_vendor');
            });

            DB::statement('ALTER TABLE booking_modifications DROP COLUMN draft_slot');

            DB::statement(
                'ALTER TABLE booking_modifications '
                ."MODIFY status ENUM('pending','customer_accepted','customer_rejected','withdrawn','expired') "
                ."NOT NULL DEFAULT 'pending'"
            );

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS bk_modifs_one_draft_per_vendor');
        }
    }
};
