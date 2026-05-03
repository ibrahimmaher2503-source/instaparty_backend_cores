<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->string('owner_type', 50);
            $table->unsignedBigInteger('owner_id');
            $table->char('currency', 3);

            $table->bigInteger('balance_minor')->default(0);
            $table->bigInteger('pending_withdrawal_minor')->default(0);

            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'currency'], 'wallets_owner_currency_unique');
            $table->index(['owner_type', 'owner_id'], 'wallets_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
