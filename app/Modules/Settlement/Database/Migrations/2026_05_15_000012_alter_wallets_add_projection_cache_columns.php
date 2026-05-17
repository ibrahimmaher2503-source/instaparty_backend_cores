<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            // High-water mark: the wallet_ledger.id that this cache reflects
            $table->unsignedBigInteger('last_ledger_entry_id')
                ->nullable()
                ->after('pending_withdrawal_minor');

            // When the projection cache was last written
            $table->timestamp('last_projected_at')
                ->nullable()
                ->after('last_ledger_entry_id');

            // FK — nullable because new wallets start with no ledger entries
            $table->foreign('last_ledger_entry_id', 'wallets_last_entry_fk')
                ->references('id')
                ->on('wallet_ledger')
                ->restrictOnDelete();

            // Index for freshness queries during reconciliation
            $table->index('last_projected_at', 'wallets_last_projected_index');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropForeign('wallets_last_entry_fk');
            $table->dropIndex('wallets_last_projected_index');
            $table->dropColumn(['last_ledger_entry_id', 'last_projected_at']);
        });
    }
};
