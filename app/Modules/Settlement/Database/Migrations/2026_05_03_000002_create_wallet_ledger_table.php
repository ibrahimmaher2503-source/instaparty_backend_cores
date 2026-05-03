<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_ledger', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();

            $table->enum('entry_type', [
                'commission_credit',
                'refund_debit',
                'withdrawal_debit',
                'manual_adjustment',
            ])->index();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3);

            $table->string('description_key', 100)->nullable();
            $table->json('description_params')->nullable();

            $table->string('related_entity_type', 50)->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();

            // Append-only: created_at only, no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at'], 'wallet_ledger_wallet_created_index');
            $table->unique(
                ['related_entity_type', 'related_entity_id', 'entry_type'],
                'wallet_ledger_entity_entry_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger');
    }
};
