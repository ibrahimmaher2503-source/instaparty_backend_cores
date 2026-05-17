<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookings', 'total_vat_minor')) {
            return;
        }
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('total_vat_minor')->default(0)->after('total_minor');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('total_vat_minor');
        });
    }
};
