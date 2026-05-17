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
        Schema::create('ledger_transaction_groups', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->enum('kind', [
                'payment_capture',
                'refund',
                'commission_accrual',
                'commission_reversal',
                'withdrawal_reserve',
                'withdrawal_settle',
                'withdrawal_reject_release',
                'manual_adjustment',
                'suspense_movement',
                'legacy_backfill',
            ]);

            $table->char('currency', 3);

            // Aggregate totals maintained by AFTER INSERT trigger on wallet_ledger
            // CHECK constraint enforces balance: total_debits_minor = total_credits_minor
            $table->unsignedBigInteger('total_debits_minor')->default(0);
            $table->unsignedBigInteger('total_credits_minor')->default(0);
            $table->unsignedInteger('entry_count')->default(0);

            // Causal chain
            $table->char('correlation_id', 26);
            $table->char('causation_id', 26)->nullable();

            // Idempotency — sparse unique (MySQL UNIQUE allows multiple NULLs)
            $table->string('idempotency_key', 128)->nullable();

            // Who triggered this group
            $table->foreignId('initiated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('initiator_type', ['system', 'webhook', 'admin', 'vendor', 'customer', 'scheduler'])->default('system');

            // Translatable description for admin display
            $table->string('description_key', 100)->nullable();
            $table->json('description_params')->nullable();

            // Free-form context (gateway response id, batch id, etc.)
            $table->json('metadata')->nullable();

            $table->timestamp('posted_at');
            $table->timestamp('created_at')->useCurrent();
        });

        // Indexes (declared outside the create closure for readability)
        Schema::table('ledger_transaction_groups', function (Blueprint $table): void {
            $table->unique('idempotency_key', 'ltg_idempotency_unique');
            $table->index(['kind', 'created_at'], 'ltg_kind_created_index');
            $table->index('correlation_id', 'ltg_correlation_index');
            $table->index('causation_id', 'ltg_causation_index');
            $table->index('initiated_by_user_id', 'ltg_user_index');
        });

        // CHECK constraint: debits must equal credits (MySQL only; SQLite doesn't support ADD CONSTRAINT via ALTER TABLE)
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE ledger_transaction_groups
                 ADD CONSTRAINT ltg_balanced_check
                 CHECK (total_debits_minor = total_credits_minor OR (total_debits_minor = 0 AND total_credits_minor = 0))'
            );
        }

        // Now that the group table exists, add the FK on wallet_ledger.transaction_group_id
        Schema::table('wallet_ledger', function (Blueprint $table): void {
            $table->foreign('transaction_group_id', 'wallet_ledger_group_fk')
                ->references('id')
                ->on('ledger_transaction_groups')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_ledger', function (Blueprint $table): void {
            $table->dropForeign('wallet_ledger_group_fk');
        });

        Schema::dropIfExists('ledger_transaction_groups');
    }
};
