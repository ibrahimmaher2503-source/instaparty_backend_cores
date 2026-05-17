<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_findings', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('reconciliation_run_id')
                ->constrained('reconciliation_runs')
                ->cascadeOnDelete();

            $table->enum('finding_type', [
                'wallet_cache_drift',
                'orphaned_refund_row',
                'orphaned_ledger_entry',
                'unbalanced_transaction_group',
                'commission_without_snapshot_rate',
                'withdrawal_without_reserve_entry',
                'negative_vendor_balance',
                'currency_mismatch',
            ]);

            $table->enum('severity', ['info', 'warning', 'high']);

            // Which resource triggered the finding
            $table->string('resource_type', 50)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();

            // Expected vs actual (JSON for flexibility)
            $table->json('expected')->nullable();
            $table->json('actual')->nullable();
            $table->json('delta')->nullable();

            // Resolution — null while pending; only these columns may be updated
            $table->enum('resolution', [
                'auto_repaired',
                'manual_review_required',
                'ignored_known_issue',
                'superseded_by_later_run',
            ])->nullable();

            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('resolved_at')->nullable();

            // Translatable description for admin display
            $table->string('description_key', 100)->nullable();
            $table->json('description_params')->nullable();

            // Append-only: no updated_at
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index(['reconciliation_run_id', 'severity'], 'findings_run_severity_index');
            $table->index(['finding_type', 'severity', 'created_at'], 'findings_type_severity_index');
            $table->index('resolution', 'findings_resolution_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_findings');
    }
};
