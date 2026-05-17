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
        Schema::rename('booking_state_transitions', 'state_transitions');

        Schema::table('state_transitions', function (Blueprint $table): void {
            $table->text('reason')->nullable()->after('trigger_kind');
            $table->char('trace_id', 36)->nullable()->after('reason');
            $table->index('trace_id', 'st_trace_id_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE state_transitions MODIFY COLUMN trigger_kind
                ENUM('system','customer','vendor','admin','admin_override') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE state_transitions MODIFY COLUMN trigger_kind
                ENUM('system','customer','vendor','admin') NOT NULL");
        }

        Schema::table('state_transitions', function (Blueprint $table): void {
            $table->dropIndex('st_trace_id_idx');
            $table->dropColumn(['trace_id', 'reason']);
        });

        Schema::rename('state_transitions', 'booking_state_transitions');
    }
};
