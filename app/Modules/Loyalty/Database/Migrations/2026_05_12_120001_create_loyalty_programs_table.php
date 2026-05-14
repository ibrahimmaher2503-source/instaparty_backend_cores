<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('vendor_profile_id')
                ->unique()
                ->constrained('vendor_profiles')
                ->restrictOnDelete();

            $table->boolean('is_active')->default(false);

            $table->decimal('points_per_currency_unit', 10, 4)->default(1.0000);

            $table->unsignedBigInteger('points_value_minor');
            $table->char('points_value_currency', 3)->default('EGP');

            $table->unsignedInteger('min_points_to_redeem')->default(100);
            $table->unsignedTinyInteger('max_redeem_pct')->default(50);
            $table->unsignedSmallInteger('points_expire_after_days')->nullable();

            $table->json('name');
            $table->json('terms')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_profile_id', 'is_active'], 'loyalty_programs_vendor_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};
