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
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        // The Settlement migration creates an expression-based UNIQUE INDEX
        // (IFNULL(...) on category_id, product_type). On SQLite, Doctrine's
        // table-rebuild for FK addition can't preserve expression columns and
        // emits empty `()` parens. Drop the index first on every driver.
        if ($isSqlite) {
            DB::statement('DROP INDEX IF EXISTS commission_rates_cat_type_unique');
        } else {
            DB::statement('DROP INDEX commission_rates_cat_type_unique ON commission_rates');
        }

        Schema::table('commission_rates', function (Blueprint $table): void {
            $table->foreignId('subscription_plan_id')
                ->nullable()
                ->after('id')
                ->constrained('subscription_plans')
                ->nullOnDelete();

            $table->index(['subscription_plan_id', 'product_type', 'category_id']);
        });

        if (! $isSqlite) {
            DB::statement(
                "CREATE UNIQUE INDEX commission_rates_cat_type_unique
                 ON commission_rates (IFNULL(category_id, 0), IFNULL(product_type, ''), IFNULL(subscription_plan_id, 0))"
            );
        }
    }

    public function down(): void
    {
        Schema::table('commission_rates', function (Blueprint $table): void {
            $table->dropForeign(['subscription_plan_id']);
            $table->dropIndex(['subscription_plan_id', 'product_type', 'category_id']);
            $table->dropColumn('subscription_plan_id');
        });
    }
};
