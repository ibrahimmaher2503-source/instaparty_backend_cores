<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor-portal 13.1–13.5 / G13 (schema addition approved by Ibrahim
 * 2026-06-05, "do all"): multiple bank accounts per vendor. The legacy
 * single implicit account stays on vendor_profiles.bank_* — withdrawals
 * keep reading it until a follow-up migrates payout selection to this
 * table (documented in the verification report).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bank_accounts', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('vendor_profile_id')->constrained()->cascadeOnDelete();

            $table->string('bank_name', 120);
            $table->string('account_holder', 120);
            $table->string('iban', 34);
            $table->string('swift_bic', 11)->nullable();
            $table->string('branch', 120)->nullable();
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->unique(['vendor_profile_id', 'iban'], 'uq_vnd_bank_iban');
            $table->index(['vendor_profile_id', 'is_default'], 'idx_vnd_bank_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bank_accounts');
    }
};
