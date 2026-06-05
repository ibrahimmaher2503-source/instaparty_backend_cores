<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor-portal 8.3–8.5 (schema addition approved by Ibrahim 2026-06-05,
 * "do all"): vendor-level blocked dates (holidays). Feeds both the vendor
 * portal/mobile and the customer-facing availability blocked_dates (which
 * has returned [] since Phase 3 C1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_blocked_dates', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('vendor_profile_id')->constrained()->cascadeOnDelete();
            $table->date('blocked_date');
            $table->json('reason')->nullable(); // translatable {en, ar}
            $table->timestamps();

            $table->unique(['vendor_profile_id', 'blocked_date'], 'uq_vnd_blocked_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_blocked_dates');
    }
};
