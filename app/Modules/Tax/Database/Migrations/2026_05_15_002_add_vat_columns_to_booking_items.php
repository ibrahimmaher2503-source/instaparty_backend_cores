<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('vat_rate_bps')->default(0)->after('unit_price_minor');
            $table->unsignedBigInteger('vat_amount_minor')->default(0)->after('vat_rate_bps');
        });
    }

    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table): void {
            $table->dropColumn(['vat_rate_bps', 'vat_amount_minor']);
        });
    }
};
