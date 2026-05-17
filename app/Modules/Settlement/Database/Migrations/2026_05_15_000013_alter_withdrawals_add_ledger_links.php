<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->string('idempotency_key', 128)->nullable()->unique('withdrawals_idempotency_unique')->after('status');

            $table->unsignedBigInteger('reserved_ledger_entry_id')->nullable()->after('idempotency_key');
            $table->unsignedBigInteger('settled_ledger_entry_id')->nullable()->after('reserved_ledger_entry_id');
            $table->unsignedBigInteger('rejected_ledger_entry_id')->nullable()->after('settled_ledger_entry_id');

            $table->foreign('reserved_ledger_entry_id', 'withdrawals_reserved_entry_fk')
                ->references('id')
                ->on('wallet_ledger')
                ->restrictOnDelete();

            $table->foreign('settled_ledger_entry_id', 'withdrawals_settled_entry_fk')
                ->references('id')
                ->on('wallet_ledger')
                ->restrictOnDelete();

            $table->foreign('rejected_ledger_entry_id', 'withdrawals_rejected_entry_fk')
                ->references('id')
                ->on('wallet_ledger')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropForeign('withdrawals_reserved_entry_fk');
            $table->dropForeign('withdrawals_settled_entry_fk');
            $table->dropForeign('withdrawals_rejected_entry_fk');
            $table->dropUnique('withdrawals_idempotency_unique');
            $table->dropColumn([
                'idempotency_key',
                'reserved_ledger_entry_id',
                'settled_ledger_entry_id',
                'rejected_ledger_entry_id',
            ]);
        });
    }
};
