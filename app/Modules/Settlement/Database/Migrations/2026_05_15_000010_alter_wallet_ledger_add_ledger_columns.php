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
        // Step 1: widen the entry_type ENUM to include all new types (MySQL only; SQLite has no strict ENUM)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE wallet_ledger
                MODIFY entry_type ENUM(
                    'commission_credit',
                    'refund_debit',
                    'withdrawal_debit',
                    'manual_adjustment',
                    'payment_capture',
                    'refund_credit_customer',
                    'refund_debit_platform',
                    'commission_accrual',
                    'commission_reversal',
                    'withdrawal_reserve',
                    'withdrawal_settle',
                    'withdrawal_reject_release',
                    'manual_adjustment_debit',
                    'manual_adjustment_credit',
                    'suspense_movement',
                    'legacy_backfill',
                    'vendor_credit'
                ) NOT NULL
            ");
        }

        // Step 2: change amount_minor from signed BIGINT to UNSIGNED
        // Backfill will move sign into direction column first via ledger:backfill
        // For now add as nullable first, make NOT NULL after backfill (T053)
        Schema::table('wallet_ledger', function (Blueprint $table): void {
            // New columns — nullable initially; backfill (T053) populates them
            // then T019 trigger enforces non-null on new rows
            $table->enum('direction', ['debit', 'credit'])->nullable()->after('entry_type');
            $table->bigInteger('running_balance_minor')->nullable()->after('amount_minor');

            // Group + correlation identifiers
            $table->unsignedBigInteger('transaction_group_id')->nullable()->after('currency');
            $table->string('counter_account_type', 50)->nullable()->after('transaction_group_id');
            $table->unsignedBigInteger('counter_account_id')->nullable()->after('counter_account_type');
            $table->char('correlation_id', 26)->nullable()->after('counter_account_id');
            $table->char('causation_id', 26)->nullable()->after('correlation_id');
            $table->string('idempotency_key', 128)->nullable()->after('causation_id');

            // Application-set posted_at (equals created_at in steady state)
            $table->timestamp('posted_at')->nullable()->after('related_entity_id');

            // Indexes
            $table->index('transaction_group_id', 'wallet_ledger_group_index');
            $table->index('correlation_id', 'wallet_ledger_correlation_index');
            $table->index('causation_id', 'wallet_ledger_causation_index');
            $table->index(['wallet_id', 'id'], 'wallet_ledger_wallet_id_desc_index');
            $table->unique(['wallet_id', 'idempotency_key'], 'wallet_ledger_wallet_idempotency_unique');

            // FK on transaction_group_id added after the group table is created (T009)
        });
    }

    public function down(): void
    {
        Schema::table('wallet_ledger', function (Blueprint $table): void {
            $table->dropIndex('wallet_ledger_group_index');
            $table->dropIndex('wallet_ledger_correlation_index');
            $table->dropIndex('wallet_ledger_causation_index');
            $table->dropIndex('wallet_ledger_wallet_id_desc_index');
            $table->dropUnique('wallet_ledger_wallet_idempotency_unique');

            $table->dropColumn([
                'direction',
                'running_balance_minor',
                'transaction_group_id',
                'counter_account_type',
                'counter_account_id',
                'correlation_id',
                'causation_id',
                'idempotency_key',
                'posted_at',
            ]);
        });

        // Revert ENUM (MySQL only)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE wallet_ledger
                MODIFY entry_type ENUM(
                    'commission_credit',
                    'refund_debit',
                    'withdrawal_debit',
                    'manual_adjustment'
                ) NOT NULL
            ");
        }
    }
};
