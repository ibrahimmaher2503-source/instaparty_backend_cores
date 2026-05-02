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
            $table->timestamp('payment_hold_expires_at')->nullable()->after('submitted_at');
            $table->index(['payment_status', 'payment_hold_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['payment_status', 'payment_hold_expires_at']);
            $table->dropColumn('payment_hold_expires_at');
        });
    }
};
