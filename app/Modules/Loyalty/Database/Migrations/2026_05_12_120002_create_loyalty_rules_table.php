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

            $table->foreignId('loyalty_program_id')
                ->constrained('loyalty_programs')
                ->cascadeOnDelete();

            $table->enum('rule_kind', [
                'first_booking',
                'category_bonus',
                'threshold_bonus',
                'referral',
            ]);

            $table->decimal('multiplier', 4, 2)->default(1.00);
            $table->json('conditions')->nullable();
            $table->json('label');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(
                ['loyalty_program_id', 'is_active', 'starts_at', 'ends_at'],
                'loyalty_rules_program_active_window_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rules');
    }
};
