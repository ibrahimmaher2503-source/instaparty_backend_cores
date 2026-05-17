<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->unsignedBigInteger('ledger_group_id')->nullable()->after('status');
            $table->string('idempotency_key', 128)->nullable()->unique('refunds_idempotency_unique')->after('ledger_group_id');

            $table->foreign('ledger_group_id', 'refunds_ledger_group_fk')
                ->references('id')
                ->on('ledger_transaction_groups')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropForeign('refunds_ledger_group_fk');
            $table->dropUnique('refunds_idempotency_unique');
            $table->dropColumn(['ledger_group_id', 'idempotency_key']);
        });
    }
};
