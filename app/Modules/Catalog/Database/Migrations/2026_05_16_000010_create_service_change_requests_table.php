<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces one open change request per service.
 *
 * MySQL 8: generated column `open_lock_key` (stored, NULL when terminal) + UNIQUE index.
 * SQLite:  native partial unique index with WHERE clause.
 *
 * Mirrors the pattern used in booking_modifications.draft_slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_change_requests', function (Blueprint $table): void {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('service_id')
                ->constrained()
                ->restrictOnDelete();

            $table->enum('product_type', ['rental', 'sale', 'digital']);

            $table->foreignId('vendor_profile_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('submitted_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('status', [
                'pending',
                'awaiting_clarification',
                'approved',
                'rejected',
                'cancelled_vendor_suspended',
                'cancelled_service_unavailable',
            ])->default('pending');

            $table->json('proposed_changes');
            $table->json('before_snapshot');
            $table->json('vendor_note')->nullable();
            $table->json('admin_note')->nullable();
            $table->unsignedTinyInteger('clarification_round')->default(0);

            $table->foreignId('decided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('decided_at')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // Admin queue pagination
            $table->index(['status', 'product_type', 'created_at'], 'scr_status_type_created_idx');

            // Vendor's pending edits screen
            $table->index(['vendor_profile_id', 'status'], 'scr_vendor_status_idx');

            // History view on a service
            $table->index(['service_id', 'created_at'], 'scr_service_created_idx');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Generated column: NULL when the status is terminal; UNIQUE enforces one open request per service.
            DB::statement(
                'ALTER TABLE service_change_requests '
                ."ADD COLUMN open_lock_key BIGINT UNSIGNED AS "
                ."(CASE WHEN status IN ('pending','awaiting_clarification') THEN service_id ELSE NULL END) STORED"
            );

            Schema::table('service_change_requests', function (Blueprint $table): void {
                $table->unique('open_lock_key', 'scr_open_lock_key_unique');
            });

            return;
        }

        if ($driver === 'sqlite') {
            // Native partial unique index (SQLite 3.8.9+)
            DB::statement(
                'CREATE UNIQUE INDEX scr_open_lock_key_unique '
                .'ON service_change_requests (service_id) '
                ."WHERE status IN ('pending','awaiting_clarification')"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_change_requests');
    }
};
