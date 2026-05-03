<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->json('label');
            $table->unsignedInteger('earn_points_per_minor')->default(1);
            $table->unsignedInteger('earn_minor_per_unit')->default(100);
            $table->unsignedInteger('redemption_ratio_points')->default(100);
            $table->unsignedInteger('redemption_ratio_minor')->default(1000);
            $table->unsignedInteger('min_points_to_redeem')->default(0);
            $table->unsignedSmallInteger('max_redeem_pct_bps')->default(5000);
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamps();

            $table->index(['loyalty_program_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rules');
    }
};
