<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->enum('scope_type', ['all', 'wallet', 'vendor', 'date_range', 'recent_touch']);
            $table->json('scope_params')->nullable();

            $table->enum('status', [
                'queued',
                'running',
                'clean',
                'anomalies_detected',
                'repaired',
                'requires_manual_review',
                'failed',
            ]);

            $table->foreignId('triggered_by_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('trigger_kind', ['scheduled', 'manual', 'admin_endpoint']);

            // Sparse unique: allows concurrent runs with different scopes
            $table->string('idempotency_key', 128)->nullable()->unique('reconciliation_runs_idempotency_unique');
            $table->char('correlation_id', 26);

            // Counters (updated by the run worker — status-only exception)
            $table->unsignedInteger('wallets_scanned')->default(0);
            $table->unsignedInteger('findings_count')->default(0);
            $table->unsignedInteger('auto_repaired_count')->default(0);
            $table->unsignedInteger('manual_review_count')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_message')->nullable();

            // Append-only: no updated_at; status is the status-exception column
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index(['status', 'created_at'], 'recon_runs_status_created_index');
            $table->index(['scope_type', 'completed_at'], 'recon_runs_scope_completed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
