<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->char('loyalty_redemption_public_id', 26)
                ->nullable()
                ->after('loyalty_redeemed_currency');
            $table->index('loyalty_redemption_public_id', 'bookings_loyalty_redemption_public_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_loyalty_redemption_public_id_idx');
            $table->dropColumn('loyalty_redemption_public_id');
        });
    }
};
