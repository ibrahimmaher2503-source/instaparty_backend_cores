<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_ledger', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->restrictOnDelete();

            $table->enum('direction', ['earn', 'redeem', 'expire', 'adjust']);
            $table->unsignedInteger('points');
            $table->unsignedInteger('balance_after');

            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->json('reason')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['user_id', 'vendor_profile_id', 'created_at'],
                'loyalty_ledger_user_vendor_created_idx'
            );
            $table->index(
                ['vendor_profile_id', 'direction', 'created_at'],
                'loyalty_ledger_vendor_direction_created_idx'
            );
            $table->index('expires_at', 'loyalty_ledger_expires_at_idx');
            $table->index(['reference_type', 'reference_id'], 'loyalty_ledger_reference_idx');
        });

        // Virtual generated column + UNIQUE index for earn deduplication.
        // For direction='earn' with a reference, the composite reference_type:reference_id
        // must be unique; for any other row the generated value is NULL (NULLs ignored by UNIQUE).
        //
        // SQLite (used in tests) does not support MySQL's `ALTER TABLE ... ADD COLUMN ...
        // GENERATED ALWAYS ... VIRTUAL` in this form, so we only apply this on MySQL/MariaDB.
        // Tests enforce dedup at the application layer (Earn idempotency check before append).
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(<<<'SQL'
                ALTER TABLE loyalty_ledger
                ADD COLUMN earn_dedup_key VARCHAR(120)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN direction = 'earn' AND reference_type IS NOT NULL
                                THEN CONCAT(reference_type, ':', reference_id)
                            ELSE NULL
                        END
                    ) VIRTUAL,
                ADD UNIQUE KEY loyalty_ledger_earn_dedup_unique (earn_dedup_key)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_ledger');
    }
};
