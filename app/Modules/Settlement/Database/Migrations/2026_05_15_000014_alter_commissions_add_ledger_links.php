<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('accrual_ledger_entry_id')->nullable()->after('status');
            $table->unsignedBigInteger('reversal_ledger_entry_id')->nullable()->after('accrual_ledger_entry_id');
            $table->string('idempotency_key', 128)->nullable()->unique('commissions_idempotency_unique')->after('reversal_ledger_entry_id');

            $table->foreign('accrual_ledger_entry_id', 'commissions_accrual_entry_fk')
                ->references('id')
                ->on('wallet_ledger')
                ->restrictOnDelete();

            $table->foreign('reversal_ledger_entry_id', 'commissions_reversal_entry_fk')
                ->references('id')
                ->on('wallet_ledger')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table): void {
            $table->dropForeign('commissions_accrual_entry_fk');
            $table->dropForeign('commissions_reversal_entry_fk');
            $table->dropUnique('commissions_idempotency_unique');
            $table->dropColumn([
                'accrual_ledger_entry_id',
                'reversal_ledger_entry_id',
                'idempotency_key',
            ]);
        });
    }
};
