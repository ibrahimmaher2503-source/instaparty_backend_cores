<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_inventory_reservations', function (Blueprint $table): void {
            $table->timestamp('released_at')->nullable()->after('expires_at');
            $table->string('release_reason', 50)->nullable()->after('released_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_inventory_reservations', function (Blueprint $table): void {
            $table->dropColumn(['released_at', 'release_reason']);
        });
    }
};
