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
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable()->after('paid_at');
            $table->foreignId('approved_by_admin_id')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('paid_by_admin_id')
                ->nullable()
                ->after('approved_by_admin_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('bank_transfer_reference', 120)->nullable()->after('paid_by_admin_id');
            $table->json('admin_payment_note')->nullable()->after('bank_transfer_reference');

            $table->unique(
                ['vendor_profile_id', 'bank_transfer_reference'],
                'withdrawals_vendor_transfer_ref_unique'
            );
        });

        // One-time backfill: historical paid rows get the new audit columns from the old combined columns
        DB::statement("
            UPDATE withdrawals
            SET
                approved_at            = processed_at,
                approved_by_admin_id   = processed_by_user_id,
                paid_by_admin_id       = processed_by_user_id
            WHERE status = 'paid'
              AND processed_at IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropUnique('withdrawals_vendor_transfer_ref_unique');
            $table->dropForeign(['approved_by_admin_id']);
            $table->dropForeign(['paid_by_admin_id']);
            $table->dropColumn([
                'approved_at',
                'approved_by_admin_id',
                'paid_by_admin_id',
                'bank_transfer_reference',
                'admin_payment_note',
            ]);
        });
    }
};
