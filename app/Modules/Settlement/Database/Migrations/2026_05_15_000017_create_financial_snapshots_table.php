<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_snapshots', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();

            $table->timestamp('snapshot_at');

            // High-water mark: the ledger entry this snapshot was built from
            $table->unsignedBigInteger('as_of_ledger_entry_id');
            $table->foreign('as_of_ledger_entry_id', 'snapshots_ledger_entry_fk')
                ->references('id')
                ->on('wallet_ledger')
                ->restrictOnDelete();

            // Balance at snapshot time
            $table->bigInteger('available_minor');         // signed: platform suspense may go negative
            $table->unsignedBigInteger('pending_minor');
            $table->char('currency', 3);

            // SHA-256 of the deterministic ledger replay used to produce this snapshot
            $table->char('checksum', 64);

            // Append-only: created_at only
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index(['wallet_id', 'snapshot_at'], 'snapshots_wallet_at_index');
            $table->index('snapshot_at', 'snapshots_at_global_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_snapshots');
    }
};
