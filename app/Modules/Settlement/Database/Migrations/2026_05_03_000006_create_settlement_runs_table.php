<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_runs', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->date('period_start');
            $table->date('period_end');

            $table->bigInteger('total_gross_minor')->default(0);
            $table->char('total_gross_currency', 3)->default('EGP');

            $table->bigInteger('total_commission_minor')->default(0);
            $table->char('total_commission_currency', 3)->default('EGP');

            $table->bigInteger('total_vendor_share_minor')->default(0);
            $table->char('total_vendor_share_currency', 3)->default('EGP');

            $table->enum('status', ['pending', 'reconciled', 'disputed'])->default('pending');

            $table->timestamps();

            $table->index(['period_start', 'period_end'], 'settlement_runs_period_index');
            $table->index(['status', 'created_at'], 'settlement_runs_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_runs');
    }
};
